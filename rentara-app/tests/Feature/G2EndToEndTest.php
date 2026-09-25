<?php

namespace Tests\Feature;

use App\ApplicationStatus;
use App\BookingStatus;
use App\IdentityDocumentReviewStatus;
use App\IdentityDocumentType;
use App\InvoiceStatus;
use App\InvoiceType;
use App\ListingStatus;
use App\Models\Complaint;
use App\Models\IdentityDocument;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Property;
use App\Models\RentalApplication;
use App\Models\Unit;
use App\Models\User;
use App\OrganizationRole;
use App\PropertyType;
use App\UnitStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class G2EndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_mvp_rental_flow_reaches_a_complaint(): void
    {
        Carbon::setTestNow('2026-01-01 09:00:00');
        Storage::fake('identity_documents');
        Storage::fake('contracts');

        $owner = User::factory()->create();
        $applicant = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->memberships()->create([
            'user_id' => $owner->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'property_type' => PropertyType::Kost,
            'identity_document_requirements' => [IdentityDocumentType::Ktp->value],
        ]);
        $unit = Unit::factory()->create([
            'property_id' => $property->id,
            'status' => UnitStatus::Available,
            'monthly_price' => 1800000,
        ]);
        $listing = $property->ensureDraftListing();
        $listing->forceFill(['status' => ListingStatus::Approved, 'published_at' => now()])->save();

        $this->actingAs($applicant)
            ->post(route('listings.applications.store', $listing->slug), [
                'unit_id' => $unit->id,
                'requested_move_in' => '2026-01-15',
                'requested_duration_months' => 12,
                'privacy_consent' => true,
            ])
            ->assertRedirect();
        $application = RentalApplication::query()->firstOrFail();

        $this->actingAs($applicant)
            ->post(route('applications.identity-documents.store', $application), [
                'document_type' => IdentityDocumentType::Ktp->value,
                'document' => UploadedFile::fake()->image('ktp.png', 900, 600),
            ])
            ->assertSessionHas('status', 'identity-document-uploaded');
        $document = IdentityDocument::query()->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('organizations.applications.identity-documents.review', [$organization, $application, $document]), [
                'review_status' => 'accepted',
            ])
            ->assertSessionHas('status', 'identity-document-reviewed');
        $this->assertSame(IdentityDocumentReviewStatus::Accepted, $document->fresh()->review_status);

        $this->actingAs($owner)
            ->patch(route('organizations.applications.status.update', [$organization, $application]), ['status' => 'approved'])
            ->assertSessionHas('status', 'application-status-updated');
        $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);

        $terms = ['monthly_rent' => 1800000, 'deposit_amount' => 500000];
        $this->actingAs($owner)
            ->post(route('organizations.applications.agreement.upsert', [$organization, $application]), [
                'terms_snapshot' => $terms,
                'contract_file' => UploadedFile::fake()->createWithContent('agreement.pdf', '%PDF-1.4 agreement'),
            ])
            ->assertRedirect();
        $this->actingAs($owner)
            ->post(route('organizations.applications.agreement.approve', [$organization, $application]))
            ->assertRedirect();
        $this->actingAs($applicant)
            ->post(route('applications.agreement.approve', $application))
            ->assertRedirect();

        $application = $application->fresh();
        $this->assertSame(ApplicationStatus::Verified, $application->status);
        $this->assertSame(BookingStatus::Verified, $application->booking->status);

        $this->actingAs($owner)
            ->post(route('organizations.bookings.confirm-payment', [$organization, $application->booking]), [
                'amount' => 1800000,
                'reference' => 'BOOKING-1',
            ])
            ->assertRedirect();

        $tenancy = $application->fresh()->tenancy;
        $this->assertSame('active', $tenancy->status->value);
        $this->assertSame(UnitStatus::Unavailable, $unit->fresh()->status);
        $this->assertDatabaseHas('invoices', [
            'tenancy_id' => $tenancy->id,
            'type' => InvoiceType::Deposit->value,
            'amount' => 500000,
            'status' => InvoiceStatus::Unpaid->value,
        ]);

        Carbon::setTestNow('2026-01-15 09:00:00');
        $this->artisan('invoices:generate-rent')->assertSuccessful();
        $rentInvoice = Invoice::query()
            ->where('tenancy_id', $tenancy->id)
            ->where('type', InvoiceType::Rent->value)
            ->firstOrFail();
        $this->assertSame('2026-01-15', $rentInvoice->period_start->toDateString());

        $this->actingAs($applicant)
            ->post(route('complaints.store'), [
                'tenancy_id' => $tenancy->id,
                'category' => 'maintenance',
                'title' => 'Broken tap',
                'description' => 'The tap leaks.',
                'priority' => 'normal',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('complaints', [
            'tenancy_id' => $tenancy->id,
            'tenant_id' => $applicant->id,
            'title' => 'Broken tap',
        ]);
        $this->assertInstanceOf(Complaint::class, Complaint::query()->latest('id')->first());

        Carbon::setTestNow();
    }
}
