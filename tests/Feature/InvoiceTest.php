<?php

namespace Tests\Feature;

use App\Enums\{ContractStatus, InvoiceStatus};
use App\Enums\PlatformRole;
use App\Enums\WorkspaceMemberRole;
use App\Models\{AuditLog, Invoice, Property, PropertyAssignment, RentalContract, Tenant, Unit, User, Workspace, WorkspaceMember};
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceMemberRole::Owner]);

        return [$owner, $workspace];
    }

    private function invoice(Workspace $workspace, User $owner, array $overrides = []): Invoice
    {
        $property = Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $unit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
        $contract = RentalContract::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'unit_id' => $unit->id, 'status' => ContractStatus::Active, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id]);
        $contract->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);

        return Invoice::factory()->create(array_merge([
            'workspace_id' => $workspace->id, 'property_id' => $property->id, 'contract_id' => $contract->id,
            'tenant_id' => $tenant->id, 'created_by' => $owner->id,
        ], $overrides));
    }

    private function workspaceSession(Workspace $workspace): array
    {
        return ['current_workspace_id' => $workspace->id];
    }

    private function payload(Invoice $invoice, array $overrides = []): array
    {
        return array_merge([
            'contract_id' => $invoice->contract_id, 'tenant_id' => $invoice->tenant_id,
            'invoice_number' => $invoice->invoice_number, 'period_start' => '2026-01-01',
            'period_end' => '2026-01-31', 'due_date' => '2026-02-01', 'amount' => 125000,
        ], $overrides);
    }

    public function test_owner_can_create_list_show_update_and_pay_an_invoice(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner, ['invoice_number' => 'INV-OLD']);
        $session = $this->workspaceSession($workspace);

        $this->actingAs($owner)->withSession($session)->get(route('app.invoices.index'))->assertOk();
        $this->actingAs($owner)->withSession($session)->get(route('app.invoices.show', $invoice))->assertOk();
        $this->actingAs($owner)->withSession($session)->put(route('app.invoices.update', $invoice), $this->payload($invoice, ['invoice_number' => 'INV-NEW']))->assertRedirect();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'invoice_number' => 'INV-NEW', 'amount' => 125000]);
        $this->actingAs($owner)->withSession($session)->post(route('app.invoices.pay', $invoice), [
            'payment_date' => '2026-02-02', 'payment_method' => 'bank_transfer', 'payment_reference' => 'REF-1', 'payment_notes' => 'received',
        ])->assertRedirect();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'payment_reference' => 'REF-1']);
        $this->actingAs($owner)->withSession($session)->get(route('app.invoices.show', $invoice))
            ->assertSee('Transfer bank')
            ->assertDontSee('bank_transfer');
    }

    public function test_invoice_requires_an_active_contract_tenant_and_valid_billing_values(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        $bad = $this->payload($invoice, ['amount' => 0]);

        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.invoices.store'), $bad)->assertSessionHasErrors('amount');
        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.invoices.store'), $this->payload($invoice, ['invoice_number' => 'INV-BAD-DUE', 'due_date' => '2025-12-01']))
            ->assertSessionHasErrors('due_date');

        $otherTenant = Tenant::factory()->create(['workspace_id' => $workspace->id]);
        $response = $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.invoices.store'), $this->payload($invoice, ['invoice_number' => 'INV-BAD-TENANT', 'tenant_id' => $otherTenant->id]));
        $response->assertSessionHasErrors('tenant_id');
        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'INV-BAD-TENANT']);
    }

    public function test_invoice_number_format_is_shared_by_create_update_and_audit(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner, ['invoice_number' => 'INV-ORIGINAL']);
        $session = $this->workspaceSession($workspace);

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.invoices.store'), $this->payload($invoice, ['invoice_number' => 'INV/2026']))
            ->assertRedirect();
        $created = Invoice::where('invoice_number', 'INV/2026')->sole();
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogger::INVOICE_CREATED]);

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.invoices.update', $created), $this->payload($created, ['invoice_number' => 'INV.2026_01-01']))
            ->assertRedirect();

        foreach (['INV 2026', '/INV-2026', 'INV-2026/'] as $invalid) {
            $response = $this->actingAs($owner)->withSession($session)
                ->post(route('app.invoices.store'), $this->payload($invoice, ['invoice_number' => $invalid]));
            $response->assertSessionHasErrors('invoice_number');
            $this->assertStringContainsString('Format nomor invoice tidak valid', (string) $response->getSession()->get('errors')->get('invoice_number')[0]);

            $response = $this->actingAs($owner)->withSession($session)
                ->put(route('app.invoices.update', $created), $this->payload($created, ['invoice_number' => $invalid]));
            $response->assertSessionHasErrors('invoice_number');
        }
    }

    public function test_manager_is_property_scoped_and_staff_can_only_view_and_pay(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        foreach ([[$manager, WorkspaceMemberRole::Manager], [$staff, WorkspaceMemberRole::Staff]] as [$user, $role]) {
            WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role]);
            PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $invoice->property_id, 'user_id' => $user->id, 'created_by' => $owner->id]);
        }
        $session = $this->workspaceSession($workspace);

        $this->actingAs($manager)->withSession($session)->put(route('app.invoices.update', $invoice), $this->payload($invoice))->assertRedirect();
        $this->actingAs($staff)->withSession($session)->get(route('app.invoices.show', $invoice))->assertOk();
        $this->actingAs($staff)->withSession($session)->put(route('app.invoices.update', $invoice), $this->payload($invoice))->assertForbidden();
        $this->actingAs($staff)->withSession($session)->post(route('app.invoices.pay', $invoice), ['payment_date' => '2026-02-02', 'payment_method' => 'cash'])->assertRedirect();

        $unassigned = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $unassigned->id, 'role' => WorkspaceMemberRole::Manager]);
        $this->actingAs($unassigned)->withSession($session)->get(route('app.invoices.show', $invoice))->assertForbidden();
    }

    public function test_cross_workspace_and_superadmin_access_are_denied(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        [$other, $otherWorkspace] = $this->owner();
        $this->actingAs($other)->withSession($this->workspaceSession($otherWorkspace))->get(route('app.invoices.show', $invoice))->assertNotFound();
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->actingAs($admin)->withSession($this->workspaceSession($workspace))->get(route('app.invoices.show', $invoice))->assertForbidden();
    }

    public function test_overdue_uses_workspace_timezone_and_paid_invoices_are_not_overdue(): void
    {
        [$owner, $workspace] = $this->owner();
        $workspace->update(['timezone' => 'Pacific/Auckland']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC'));
        $invoice = $this->invoice($workspace, $owner, ['due_date' => '2026-01-01']);
        $this->assertFalse($invoice->fresh()->is_overdue);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-01 11:00:00', 'UTC'));
        $this->assertTrue($invoice->fresh()->is_overdue);
        $invoice->update(['status' => InvoiceStatus::Paid]);
        $this->assertFalse($invoice->fresh()->is_overdue);
        CarbonImmutable::setTestNow();
    }

    public function test_invoice_and_contract_period_duplicates_include_soft_deleted_rows(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner, ['invoice_number' => 'INV-DUP']);
        $invoice->delete();
        $response = $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.invoices.store'), $this->payload($invoice, ['invoice_number' => 'INV-DUP']));
        $response->assertSessionHasErrors('invoice_number');
    }

    public function test_repeated_payment_is_idempotent_but_conflicting_payment_is_rejected(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        $payload = ['payment_date' => '2026-02-02', 'payment_method' => 'cash', 'payment_reference' => 'same'];
        $session = $this->workspaceSession($workspace);
        $this->actingAs($owner)->withSession($session)->post(route('app.invoices.pay', $invoice), $payload)->assertRedirect();
        $auditCount = AuditLog::where('event', AuditLogger::INVOICE_PAYMENT_RECORDED)->count();
        $this->actingAs($owner)->withSession($session)->post(route('app.invoices.pay', $invoice), $payload)->assertRedirect();
        $this->assertSame($auditCount, AuditLog::where('event', AuditLogger::INVOICE_PAYMENT_RECORDED)->count());
        $this->actingAs($owner)->withSession($session)->post(route('app.invoices.pay', $invoice), array_merge($payload, ['payment_reference' => 'different']))->assertSessionHasErrors('invoice');
    }

    public function test_payment_date_is_stored_as_the_submitted_calendar_date(): void
    {
        [$owner, $workspace] = $this->owner();
        $workspace->update(['timezone' => 'Pacific\Honolulu']);
        $invoice = $this->invoice($workspace, $owner);

        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.invoices.pay', $invoice), ['payment_date' => '2026-02-02', 'payment_method' => 'cash'])
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'paid_date' => '2026-02-02']);
        $this->assertSame('2026-02-02', $invoice->fresh()->paid_date->toDateString());
    }

    public function test_payment_audit_failure_rolls_back_payment_and_audit_values_are_safe(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        app()->instance(AuditLogger::class, new class extends AuditLogger {
            public function invoiceEvent(string $event, ?User $actor, Invoice $invoice, ?array $old = null, ?array $new = null): void { throw new \RuntimeException('audit failed'); }
        });

        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);
        try {
            $this->actingAs($owner)->withSession($this->workspaceSession($workspace))->post(route('app.invoices.pay', $invoice), [
                'payment_date' => '2026-02-02', 'payment_method' => 'cash', 'payment_reference' => 'secret-proof', 'payment_notes' => 'private pii',
            ]);
        } finally {
            $this->assertSame('unpaid', $invoice->fresh()->status->value);
            $this->assertStringNotContainsString('secret-proof', json_encode(AuditLog::all()));
            $this->assertStringNotContainsString('private pii', json_encode(AuditLog::all()));
        }
    }

    public function test_parent_rows_cannot_be_physically_deleted_while_invoice_history_exists(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        foreach ([$invoice->contract, $invoice->property, $invoice->tenant] as $parent) {
            try {
                $parent->forceDelete();
                $this->fail('Invoice history must restrict physical parent deletion.');
            } catch (\Illuminate\Database\QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_non_billable_contract_statuses_are_rejected_on_update(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        foreach ([ContractStatus::Draft, ContractStatus::Pending, ContractStatus::Cancelled, ContractStatus::Completed, ContractStatus::Terminated] as $status) {
            $invoice->contract->update(['status' => $status]);
            $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
                ->put(route('app.invoices.update', $invoice), $this->payload($invoice, ['invoice_number' => 'INV-STATUS-'.$status->value]))
                ->assertSessionHasErrors('contract_id');
        }
    }

    public function test_payment_method_and_amount_boundaries_are_validated(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        $session = $this->workspaceSession($workspace);
        $this->actingAs($owner)->withSession($session)->post(route('app.invoices.store'), $this->payload($invoice, ['invoice_number' => 'INV-OVERFLOW', 'amount' => '9223372036854775808']))->assertSessionHasErrors('amount');
        $this->actingAs($owner)->withSession($session)->post(route('app.invoices.pay', $invoice), ['payment_date' => '2026-02-02', 'payment_method' => 'card'])->assertSessionHasErrors('payment_method');
    }

    public function test_invoice_factory_keeps_default_relationships_in_one_workspace_and_property(): void
    {
        $invoice = Invoice::factory()->create();
        $this->assertSame($invoice->workspace_id, $invoice->contract->workspace_id);
        $this->assertSame($invoice->property_id, $invoice->contract->property_id);
        $this->assertSame($invoice->property_id, $invoice->contract->unit_id ? $invoice->contract->unit->property_id : null);
        $this->assertTrue($invoice->contract->tenants()->whereKey($invoice->tenant_id)->exists());
    }

    public function test_invoice_factory_workspace_override_rebuilds_the_dependent_graph(): void
    {
        $workspace = Workspace::factory()->create();
        $invoice = Invoice::factory()->create(['workspace_id' => $workspace->id]);

        $this->assertSame($workspace->id, $invoice->workspace_id);
        $this->assertSame($workspace->id, $invoice->property->workspace_id);
        $this->assertSame($workspace->id, $invoice->contract->workspace_id);
        $this->assertSame($invoice->property_id, $invoice->contract->property_id);
        $this->assertSame($workspace->id, $invoice->contract->unit->workspace_id);
        $this->assertSame($workspace->id, $invoice->tenant->workspace_id);
        $this->assertTrue($invoice->contract->tenants()->whereKey($invoice->tenant_id)->exists());
    }

    public function test_invoice_factory_accepts_a_tenant_override_compatible_with_the_contract_unit(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $invoice->contract->unit_id]);
        $invoice->contract->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);

        $made = Invoice::factory()->make(['workspace_id' => $workspace->id, 'property_id' => $invoice->property_id, 'contract_id' => $invoice->contract_id, 'tenant_id' => $tenant->id]);

        $this->assertSame($tenant->id, $made->tenant_id);
    }

    public function test_invoice_factory_rejects_a_tenant_override_from_another_unit(): void
    {
        [$owner, $workspace] = $this->owner();
        $invoice = $this->invoice($workspace, $owner);
        $otherUnit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $invoice->property_id]);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $otherUnit->id]);

        $this->expectException(\InvalidArgumentException::class);
        Invoice::factory()->make(['workspace_id' => $workspace->id, 'property_id' => $invoice->property_id, 'contract_id' => $invoice->contract_id, 'tenant_id' => $tenant->id]);
    }
}

