<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Enums\TenantStatus;
use App\Enums\UnitStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\RentalContract;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\PropertyAssignment;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMemberRole::Owner,
        ]);

        return [$user, $workspace];
    }

    private function unit(Workspace $workspace, User $owner, int $capacity = 2): array
    {
        $property = Property::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);
        $unit = Unit::factory()->create([
            'workspace_id' => $workspace->id,
            'property_id' => $property->id,
            'capacity' => $capacity,
        ]);

        return [$property, $unit];
    }

    private function contract(Workspace $workspace, Property $property, Unit $unit, array $overrides = []): RentalContract
    {
        return RentalContract::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
        ], $overrides));
    }

    private function workspaceSession(Workspace $workspace): array
    {
        return ['current_workspace_id' => $workspace->id];
    }

    public function test_contract_crud_is_workspace_and_property_scoped(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $payload = [
            'property_id' => $property->id, 'unit_id' => $unit->id,
            'contract_number' => 'CTR-001', 'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(), 'rental_price' => 1000000,
            'deposit_amount' => 500000,
        ];

        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.store'), $payload)->assertRedirect();
        $contract = RentalContract::where('contract_number', 'CTR-001')->sole();

        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->get(route('app.contracts.show', $contract))->assertOk()->assertSee('CTR-001');
        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->put(route('app.contracts.update', $contract), array_merge($payload, ['notes' => 'updated']))
            ->assertRedirect();
        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'notes' => 'updated']);

        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->delete(route('app.contracts.destroy', $contract))->assertRedirect();
        $this->assertSoftDeleted('rental_contracts', ['id' => $contract->id]);
        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.restore', $contract))->assertRedirect();
        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contract.deleted', 'auditable_id' => $contract->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contract.restored', 'auditable_id' => $contract->id]);
    }

    public function test_contract_idor_and_role_authorization_are_denied(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $contract = $this->contract($workspace, $property, $unit);
        $outsider = User::factory()->create();
        $otherWorkspace = Workspace::factory()->create(['owner_id' => $outsider->id]);
        WorkspaceMember::factory()->create(['workspace_id' => $otherWorkspace->id, 'user_id' => $outsider->id, 'role' => WorkspaceMemberRole::Owner]);

        $staff = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $staff->id, 'role' => WorkspaceMemberRole::Staff]);
        $this->actingAs($staff)->withSession($this->workspaceSession($workspace))
            ->get(route('app.contracts.show', $contract))->assertForbidden();

        app()->forgetInstance(CurrentWorkspace::class);
        $this->actingAs($outsider)->withSession($this->workspaceSession($otherWorkspace))
            ->get(route('app.contracts.show', $contract))->assertNotFound();
    }

    public function test_activation_requires_tenants_enforces_capacity_and_syncs_unit(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner, 2);
        $contract = $this->contract($workspace, $property, $unit, ['contract_number' => 'CTR-A']);
        $session = $this->workspaceSession($workspace);

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.contracts.activate', $contract))->assertSessionHasErrors('tenant_id');
        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'status' => 'draft']);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => 'available']);
        $this->assertDatabaseMissing('audit_logs', ['auditable_id' => $contract->id]);

        $tenants = Tenant::factory()->count(2)->create(['workspace_id' => $workspace->id, 'unit_id' => null, 'status' => TenantStatus::Active]);
        foreach ($tenants as $tenant) {
            $this->actingAs($owner)->withSession($session)
                ->post(route('app.contracts.tenants.attach', $contract), ['tenant_id' => $tenant->id])->assertRedirect();
        }
        $this->actingAs($owner)->withSession($session)
            ->post(route('app.contracts.activate', $contract))->assertRedirect();
        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'status' => 'active']);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => 'occupied']);
        $this->assertSame(2, Tenant::where('unit_id', $unit->id)->count());
    }

    public function test_activation_allows_exact_union_capacity(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner, 2);
        $existing = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);
        $new = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => null]);
        $contract = $this->contract($workspace, $property, $unit);
        $contract->tenants()->attach($new->id, ['workspace_id' => $workspace->id]);

        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.activate', $contract))->assertRedirect();

        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'status' => ContractStatus::Active->value]);
        $this->assertDatabaseHas('tenants', ['id' => $existing->id, 'unit_id' => $unit->id]);
        $this->assertDatabaseHas('tenants', ['id' => $new->id, 'unit_id' => $unit->id]);
    }

    public function test_activation_does_not_double_count_a_tenant_already_assigned_to_the_unit(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner, 1);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);
        $contract = $this->contract($workspace, $property, $unit);
        $contract->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);

        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.activate', $contract))->assertRedirect();

        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'status' => ContractStatus::Active->value]);
    }

    public function test_activation_rejects_union_occupancy_overflow(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner, 2);
        Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);
        $contract = $this->contract($workspace, $property, $unit);
        $contract->tenants()->attach(
            Tenant::factory()->count(2)->create(['workspace_id' => $workspace->id, 'unit_id' => null])->pluck('id'),
            ['workspace_id' => $workspace->id]
        );

        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.activate', $contract))->assertSessionHasErrors('tenant_id');

        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'status' => ContractStatus::Draft->value]);
    }

    public function test_activation_rejects_overlapping_contracts_and_shared_tenants_are_supported(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner, 2);
        $first = $this->contract($workspace, $property, $unit, ['contract_number' => 'CTR-1', 'status' => ContractStatus::Active]);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);
        $first->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);
        $second = $this->contract($workspace, $property, $unit, ['contract_number' => 'CTR-2']);
        $second->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);

        $response = $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.activate', $second));
        $response->assertSessionHasErrors('start_date');
        $this->assertDatabaseHas('rental_contracts', ['id' => $second->id, 'status' => 'draft']);
    }

    public function test_checkin_checkout_reconcile_deposit_and_release_unit(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $contract = $this->contract($workspace, $property, $unit, ['status' => ContractStatus::Active, 'deposit_amount' => 100000]);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);
        $contract->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);
        $unit->update(['status' => UnitStatus::Occupied]);
        $session = $this->workspaceSession($workspace);

        $this->actingAs($owner)->withSession($session)->post(route('app.contracts.check-in', $contract), [
            'deposit_received' => 100000,
        ])->assertRedirect();
        $this->assertDatabaseHas('check_ins', ['rental_contract_id' => $contract->id, 'deposit_received' => 100000]);

        $this->actingAs($owner)->withSession($session)->post(route('app.contracts.check-out', $contract), [
            'deposit_returned' => 60000, 'deposit_deduction' => 40000,
        ])->assertRedirect();
        $this->assertDatabaseHas('check_outs', ['rental_contract_id' => $contract->id, 'deposit_returned' => 60000, 'deposit_deduction' => 40000]);
        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'status' => 'completed']);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => 'available']);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'unit_id' => null]);

        $contract->refresh();
        $this->assertSame(1, $contract->checkOut()->count());
    }

    public function test_invalid_deposit_reconciliation_rolls_back_and_audit_values_are_safe(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $contract = $this->contract($workspace, $property, $unit, ['status' => ContractStatus::Active, 'deposit_amount' => 10]);
        $unit->update(['status' => UnitStatus::Occupied]);
        $before = AuditLog::count();

        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.check-out', $contract), ['deposit_returned' => 11])
            ->assertSessionHasErrors('deposit_returned');
        $this->assertDatabaseCount('check_outs', 0);
        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'status' => 'active']);
        $this->assertSame($before, AuditLog::count());

        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id]);
        $contract->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);
        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.terminate', $contract), ['reason' => 'End of lease'])->assertRedirect();
        $audit = AuditLog::where('event', 'contract.terminated')->latest('id')->firstOrFail();
        $this->assertSame(['status' => 'active'], $audit->old_values);
        $this->assertSame('terminated', $audit->new_values['status']);
        $this->assertArrayNotHasKey('identity_number', $audit->new_values ?? []);
        $this->assertArrayNotHasKey('password', $audit->new_values ?? []);
    }

    public function test_assigned_manager_cannot_attach_a_tenant_outside_the_assigned_property(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        [, $otherUnit] = $this->unit($workspace, $owner);
        $manager = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $manager->id, 'role' => WorkspaceMemberRole::Manager]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $manager->id, 'created_by' => $owner->id]);
        $contract = $this->contract($workspace, $property, $unit);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $otherUnit->id]);

        $this->actingAs($manager)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.tenants.attach', $contract), ['tenant_id' => $tenant->id])
            ->assertSessionHasErrors('tenant_id');
        $this->assertDatabaseMissing('contract_tenant', ['rental_contract_id' => $contract->id, 'tenant_id' => $tenant->id]);
    }

    public function test_assigned_manager_can_attach_an_unassigned_tenant_but_staff_cannot(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $manager->id, 'role' => WorkspaceMemberRole::Manager]);
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $staff->id, 'role' => WorkspaceMemberRole::Staff]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $manager->id, 'created_by' => $owner->id]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $staff->id, 'created_by' => $owner->id]);
        $contract = $this->contract($workspace, $property, $unit);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => null, 'status' => TenantStatus::Active, 'name' => 'Tenant Belum Ditempatkan']);
        $session = $this->workspaceSession($workspace);

        $this->actingAs($manager)->withSession($session)->get(route('app.contracts.show', $contract))->assertSee('Tenant Belum Ditempatkan');
        $this->actingAs($manager)->withSession($session)->post(route('app.contracts.tenants.attach', $contract), ['tenant_id' => $tenant->id])->assertRedirect();
        $this->assertDatabaseHas('contract_tenant', ['rental_contract_id' => $contract->id, 'tenant_id' => $tenant->id]);
        $this->actingAs($staff)->withSession($session)->post(route('app.contracts.tenants.attach', $contract), ['tenant_id' => $tenant->id])->assertForbidden();
    }

    public function test_ending_contract_preserves_newer_active_tenant_assignment_then_clears_it(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $old = $this->contract($workspace, $property, $unit, ['status' => ContractStatus::Active, 'contract_number' => 'OLD']);
        $new = $this->contract($workspace, $property, $unit, ['status' => ContractStatus::Active, 'contract_number' => 'NEW']);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);
        $old->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);
        $new->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);
        $session = $this->workspaceSession($workspace);

        $this->actingAs($owner)->withSession($session)->post(route('app.contracts.terminate', $old), ['reason' => 'Kontrak selesai'])->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'unit_id' => $unit->id]);
        $this->actingAs($owner)->withSession($session)->post(route('app.contracts.cancel', $new))->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'unit_id' => null]);
    }

    public function test_assigned_manager_cannot_move_a_contract_to_an_unassigned_property(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        [$destination, $destinationUnit] = $this->unit($workspace, $owner);
        $manager = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $manager->id,
            'role' => WorkspaceMemberRole::Manager,
        ]);
        PropertyAssignment::factory()->create([
            'workspace_id' => $workspace->id,
            'property_id' => $property->id,
            'user_id' => $manager->id,
            'created_by' => $owner->id,
        ]);
        $contract = $this->contract($workspace, $property, $unit);

        $this->actingAs($manager)->withSession($this->workspaceSession($workspace))
            ->put(route('app.contracts.update', $contract), [
                'property_id' => $destination->id,
                'unit_id' => $destinationUnit->id,
                'contract_number' => $contract->contract_number,
                'start_date' => $contract->start_date->toDateString(),
                'end_date' => $contract->end_date->toDateString(),
                'rental_price' => $contract->rental_price,
                'deposit_amount' => $contract->deposit_amount,
            ])->assertForbidden();

        $this->assertDatabaseHas('rental_contracts', [
            'id' => $contract->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_update_revalidates_status_after_lifecycle_transition(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $contract = $this->contract($workspace, $property, $unit);
        $tenant = Tenant::factory()->create([
            'workspace_id' => $workspace->id,
            'unit_id' => null,
            'status' => TenantStatus::Active,
        ]);
        $session = $this->workspaceSession($workspace);
        $this->actingAs($owner)->withSession($session)
            ->post(route('app.contracts.tenants.attach', $contract), ['tenant_id' => $tenant->id])
            ->assertRedirect();
        $this->actingAs($owner)->withSession($session)
            ->post(route('app.contracts.activate', $contract))
            ->assertRedirect();
        $contract->refresh();
        $payload = [
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'contract_number' => $contract->contract_number,
            'start_date' => $contract->start_date->toDateString(),
            'end_date' => $contract->end_date->toDateString(),
            'rental_price' => $contract->rental_price,
            'deposit_amount' => $contract->deposit_amount,
            'notes' => 'must not be written',
        ];

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.contracts.update', $contract), $payload)
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('rental_contracts', ['id' => $contract->id, 'notes' => 'must not be written']);
        $this->assertDatabaseHas('rental_contracts', ['id' => $contract->id, 'status' => ContractStatus::Active->value]);
    }

    public function test_checkout_requires_exact_deposit_reconciliation(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $contract = $this->contract($workspace, $property, $unit, ['status' => ContractStatus::Active, 'deposit_amount' => 100]);
        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.check-in', $contract), ['deposit_received' => 80])->assertRedirect();
        $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.check-out', $contract), ['deposit_returned' => 70, 'deposit_deduction' => 0])
            ->assertSessionHasErrors('deposit_returned');
        $this->assertDatabaseCount('check_outs', 0);
    }

    public function test_renewal_rechecks_overlap_and_attaches_locked_active_tenants(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner, 2);
        $contract = $this->contract($workspace, $property, $unit, [
            'status' => ContractStatus::Active, 'contract_number' => 'CURRENT',
            'start_date' => now()->subYear()->toDateString(), 'end_date' => now()->subDay()->toDateString(),
        ]);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id, 'status' => TenantStatus::Active]);
        $contract->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);
        $session = $this->workspaceSession($workspace);

        $response = $this->actingAs($owner)->withSession($session)->post(route('app.contracts.renew', $contract), [
            'contract_number' => 'RENEWED', 'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(),
            'rental_price' => 100, 'deposit_amount' => 50,
        ]);
        $response->assertRedirect();
        $replacement = RentalContract::where('contract_number', 'RENEWED')->sole();
        $this->assertSame('draft', $replacement->status->value);
        $this->assertDatabaseHas('contract_tenant', ['rental_contract_id' => $replacement->id, 'tenant_id' => $tenant->id]);

        $overlap = $this->contract($workspace, $property, $unit, [
            'status' => ContractStatus::Active, 'contract_number' => 'OVERLAP',
            'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(30)->toDateString(),
        ]);
        $this->actingAs($owner)->withSession($session)->post(route('app.contracts.renew', $contract), [
            'contract_number' => 'RENEWED-2', 'start_date' => now()->addDays(15)->toDateString(), 'end_date' => now()->addDays(40)->toDateString(),
            'rental_price' => 100, 'deposit_amount' => 50,
        ])->assertSessionHasErrors('start_date');
        $this->assertDatabaseMissing('rental_contracts', ['id' => $overlap->id, 'status' => ContractStatus::Draft->value]);
        $this->assertDatabaseMissing('rental_contracts', ['contract_number' => 'RENEWED-2']);
    }

    public function test_duplicate_contract_number_is_rejected_on_create_including_soft_deleted_records(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $existing = $this->contract($workspace, $property, $unit, ['contract_number' => 'DUPLICATE']);
        $existing->delete();

        $response = $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.store'), [
                'property_id' => $property->id, 'unit_id' => $unit->id,
                'contract_number' => 'DUPLICATE', 'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(), 'rental_price' => 100,
                'deposit_amount' => 50,
            ]);

        $response->assertSessionHasErrors('contract_number');
        $this->assertDatabaseCount('rental_contracts', 1);
    }

    public function test_duplicate_contract_number_is_rejected_on_update_without_changing_id(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $first = $this->contract($workspace, $property, $unit, ['contract_number' => 'FIRST']);
        $second = $this->contract($workspace, $property, $unit, ['contract_number' => 'SECOND']);

        $response = $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->put(route('app.contracts.update', $first), [
                'property_id' => $property->id, 'unit_id' => $unit->id,
                'contract_number' => 'SECOND', 'start_date' => $first->start_date->toDateString(),
                'end_date' => $first->end_date->toDateString(), 'rental_price' => $first->rental_price,
                'deposit_amount' => $first->deposit_amount,
            ]);

        $response->assertSessionHasErrors('contract_number');
        $this->assertDatabaseHas('rental_contracts', ['id' => $first->id, 'contract_number' => 'FIRST']);
        $this->assertDatabaseHas('rental_contracts', ['id' => $second->id, 'contract_number' => 'SECOND']);
    }

    public function test_duplicate_contract_number_is_rejected_on_renewal(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $current = $this->contract($workspace, $property, $unit, [
            'status' => ContractStatus::Active, 'contract_number' => 'CURRENT',
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $this->contract($workspace, $property, $unit, ['contract_number' => 'TAKEN']);

        $response = $this->actingAs($owner)->withSession($this->workspaceSession($workspace))
            ->post(route('app.contracts.renew', $current), [
                'contract_number' => 'TAKEN', 'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(), 'rental_price' => 100,
                'deposit_amount' => 50,
            ]);

        $response->assertSessionHasErrors('contract_number');
        $this->assertDatabaseCount('rental_contracts', 2);
    }

    public function test_contract_number_can_be_reused_in_another_workspace(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        [$otherOwner, $otherWorkspace] = $this->owner();
        [$otherProperty, $otherUnit] = $this->unit($otherWorkspace, $otherOwner);
        $this->contract($workspace, $property, $unit, ['contract_number' => 'SHARED']);

        $response = $this->actingAs($otherOwner)->withSession($this->workspaceSession($otherWorkspace))
            ->post(route('app.contracts.store'), [
                'property_id' => $otherProperty->id, 'unit_id' => $otherUnit->id,
                'contract_number' => 'SHARED', 'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(), 'rental_price' => 100,
                'deposit_amount' => 50,
            ]);

        $response->assertRedirect();
        $this->assertSame(2, RentalContract::where('contract_number', 'SHARED')->count());
    }

    public function test_historical_contract_records_block_physical_parent_deletion(): void
    {
        [$owner, $workspace] = $this->owner();
        [$property, $unit] = $this->unit($workspace, $owner);
        $contract = $this->contract($workspace, $property, $unit, ['status' => ContractStatus::Completed]);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);
        $contract->tenants()->attach($tenant->id, ['workspace_id' => $workspace->id]);
        $contract->checkIn()->create(['checked_in_at' => now()]);
        $contract->checkOut()->create(['checked_out_at' => now()]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $unit->forceDelete();
    }
}
