<?php

namespace Tests\Feature;

use App\InvoiceStatus;
use App\InvoiceType;
use App\Models\AuditEvent;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Tenancy;
use App\Models\User;
use App\OrganizationRole;
use App\Services\InvoiceGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_is_created_once_and_rent_generation_is_idempotent(): void
    {
        $tenancy = Tenancy::factory()->create([
            'deposit_amount' => 500000,
            'start_date' => '2026-01-15',
            'end_date' => '2026-12-31',
        ]);
        $service = app(InvoiceGenerationService::class);

        $service->createDeposit($tenancy);
        $service->createDeposit($tenancy);
        Carbon::setTestNow('2026-02-15');
        $service->generateRentFor($tenancy->fresh(), Carbon::today()->toImmutable());
        $service->generateRentFor($tenancy->fresh(), Carbon::today()->toImmutable());

        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseHas('invoices', [
            'type' => InvoiceType::Deposit->value,
            'amount' => 500000,
            'status' => InvoiceStatus::Unpaid->value,
        ]);
        $rentInvoice = Invoice::query()->where('type', InvoiceType::Rent)->firstOrFail();
        $this->assertSame('2026-02-15', $rentInvoice->period_start->toDateString());
        $this->assertSame('2026-03-14', $rentInvoice->period_end->toDateString());
        Carbon::setTestNow();
    }

    public function test_rent_period_clamps_start_day_to_the_last_day_of_february(): void
    {
        $tenancy = Tenancy::factory()->create([
            'start_date' => '2026-01-31',
            'end_date' => '2026-04-30',
        ]);
        Carbon::setTestNow('2026-02-28');

        $invoice = app(InvoiceGenerationService::class)->generateRentFor($tenancy, Carbon::today()->toImmutable());

        $this->assertSame('2026-02-28', $invoice?->period_start->toDateString());
        $this->assertSame('2026-03-30', $invoice?->period_end->toDateString());
        Carbon::setTestNow();
    }

    public function test_rent_period_for_january_31_as_of_march_30_ends_on_march_30(): void
    {
        $tenancy = Tenancy::factory()->create([
            'start_date' => '2026-01-31',
            'end_date' => '2026-04-30',
        ]);
        Carbon::setTestNow('2026-03-30');

        $invoice = app(InvoiceGenerationService::class)->generateRentFor($tenancy, Carbon::today()->toImmutable());

        $this->assertSame('2026-02-28', $invoice?->period_start->toDateString());
        $this->assertSame('2026-03-30', $invoice?->period_end->toDateString());
        Carbon::setTestNow();
    }

    public function test_only_an_organization_owner_or_manager_can_mark_invoice_paid(): void
    {
        $tenancy = Tenancy::factory()->create(['deposit_amount' => 100]);
        $invoice = Invoice::factory()->create([
            'tenancy_id' => $tenancy->id,
            'organization_id' => $tenancy->organization_id,
            'tenant_id' => $tenancy->tenant_id,
        ]);
        $owner = User::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $otherOrganization->memberships()->create([
            'user_id' => $owner->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);

        $this->actingAs($owner)
            ->patch(route('organizations.invoices.paid', [$tenancy->organization_id, $invoice]), ['reference' => 'MANUAL-1'])
            ->assertForbidden();

        $manager = User::factory()->create();
        Organization::query()->findOrFail($tenancy->organization_id)->memberships()->create([
            'user_id' => $manager->id,
            'role' => OrganizationRole::Manager,
            'accepted_at' => now(),
        ]);
        $this->actingAs($manager)
            ->patch(route('organizations.invoices.paid', [$tenancy->organization_id, $invoice]), ['reference' => 'MANUAL-1'])
            ->assertRedirect();
        $this->actingAs($manager)
            ->patch(route('organizations.invoices.paid', [$tenancy->organization_id, $invoice]), ['reference' => 'MANUAL-2'])
            ->assertRedirect();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('MANUAL-1', $invoice->fresh()->payment_reference);
        $this->assertSame(1, AuditEvent::query()->where('action', 'invoice.marked_paid')->count());
    }

    public function test_tenant_can_upload_encrypted_evidence_only_for_own_unpaid_invoice(): void
    {
        Storage::fake('payment_evidence');
        $tenancy = Tenancy::factory()->create();
        $invoice = Invoice::factory()->create(['tenancy_id' => $tenancy->id]);
        $otherInvoice = Invoice::factory()->create();

        $this->actingAs($tenancy->tenant)
            ->post(route('invoices.payment-evidence.store', $invoice), [
                'evidence' => UploadedFile::fake()->createWithContent('receipt.pdf', '%PDF-private-receipt'),
            ])
            ->assertRedirect();

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->payment_evidence_storage_path);
        $this->assertNotSame('%PDF-private-receipt', Storage::disk('payment_evidence')->get($invoice->payment_evidence_storage_path));
        $this->assertDatabaseHas('audit_events', ['action' => 'invoice.payment_evidence_uploaded', 'subject_id' => $invoice->id]);

        $this->actingAs($tenancy->tenant)
            ->post(route('invoices.payment-evidence.store', $otherInvoice), [
                'evidence' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
            ])
            ->assertNotFound();
    }

    public function test_owner_can_reverse_paid_invoice_with_reason_and_reversal_is_idempotent(): void
    {
        $tenancy = Tenancy::factory()->create();
        $invoice = Invoice::factory()->create([
            'tenancy_id' => $tenancy->id,
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'paid_by' => $tenancy->tenant_id,
            'payment_reference' => 'MANUAL-1',
            'payment_note' => 'Received manually',
        ]);
        $owner = User::factory()->create();
        $tenancy->organization->memberships()->create([
            'user_id' => $owner->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);

        $this->actingAs($owner)
            ->patch(route('organizations.invoices.reverse', [$tenancy->organization, $invoice]), ['reason' => 'Payment was recorded against the wrong invoice.'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->patch(route('organizations.invoices.reverse', [$tenancy->organization, $invoice]), ['reason' => 'Another valid correction reason.'])
            ->assertRedirect();

        $invoice = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertNull($invoice->paid_at);
        $this->assertNull($invoice->paid_by);
        $this->assertNull($invoice->payment_reference);
        $this->assertSame(1, AuditEvent::query()->where('action', 'invoice.payment_reversed')->count());
    }
}
