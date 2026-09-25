<?php

namespace Tests\Feature;

use App\ApplicationStatus;
use App\BookingStatus;
use App\ListingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\Property;
use App\Models\RentalApplication;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use App\OrganizationRole;
use App\PropertyType;
use App\UnitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class RentalAgreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_organization_manager_and_applicant_can_approve_and_both_approvals_verify(): void
    {
        Storage::fake('contracts');
        [$application, $owner, $applicant, $organization] = $this->application();
        $staff = User::factory()->create();
        $organization->memberships()->create(['user_id' => $staff->id, 'role' => OrganizationRole::Staff, 'accepted_at' => now()]);

        $this->actingAs($staff)->post(route('organizations.applications.agreement.upsert', [$organization, $application]), [
            'terms_snapshot' => ['rent' => 1800000],
            'contract_file' => UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 contract'),
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('organizations.applications.agreement.upsert', [$organization, $application]), [
            'terms_snapshot' => ['rent' => 1800000],
            'contract_file' => UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 contract'),
        ])->assertRedirect();
        $agreement = $application->fresh()->rentalAgreement;
        $this->assertSame('%PDF-1.4 contract', Crypt::decryptString(Storage::disk('contracts')->get($agreement->contract_storage_path)));

        $this->actingAs($owner)->post(route('organizations.applications.agreement.approve', [$organization, $application]))->assertRedirect();
        $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);
        $this->assertDatabaseCount('bookings', 0);
        $this->actingAs($applicant)->post(route('applications.agreement.approve', $application))->assertRedirect();
        $this->assertSame(ApplicationStatus::Verified, $application->fresh()->status);
        $this->assertSame(BookingStatus::Verified, $application->fresh()->booking->status);
        $this->assertSame(UnitStatus::Unavailable, $application->unit->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'rental_agreement.status_changed']);
    }

    public function test_owner_can_confirm_manual_payment_once_and_other_organizations_cannot_access_booking(): void
    {
        Storage::fake('contracts');
        [$application, $owner, $applicant, $organization] = $this->application();
        $this->createApprovedAgreement($application, $owner, $applicant, $organization);
        $booking = $application->fresh()->booking;
        $otherOrganization = Organization::factory()->create();
        $otherUser = User::factory()->create();
        $auditCount = fn (): int => AuditEvent::query()->where('action', 'booking.payment_confirmed')->count();

        $this->actingAs($otherUser)
            ->post(route('organizations.bookings.confirm-payment', [$otherOrganization, $booking]))
            ->assertForbidden();

        $this->actingAs($owner)->post(route('organizations.bookings.confirm-payment', [$organization, $booking]), [
            'amount' => 1800000,
            'reference' => 'CASH-1',
            'note' => 'Received outside platform',
        ])->assertRedirect();
        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        $this->assertDatabaseHas('tenancies', [
            'booking_id' => $booking->id,
            'tenant_id' => $application->applicant_id,
            'organization_id' => $organization->id,
            'unit_id' => $application->unit_id,
            'status' => 'active',
            'monthly_rent' => 1800000,
        ]);
        $this->assertSame(['rent' => 1800000], $booking->fresh()->tenancy->terms_snapshot);
        $this->assertDatabaseHas('audit_events', ['action' => 'tenancy.created', 'subject_id' => $booking->tenancy->id]);
        $this->assertSame(1, $auditCount());
        $this->actingAs($owner)->post(route('organizations.bookings.confirm-payment', [$organization, $booking]), [
            'amount' => 1800000,
        ])->assertRedirect();
        $this->assertSame(1, $auditCount());
        $this->assertSame(1, Tenancy::query()->where('booking_id', $booking->id)->count());
    }

    public function test_agreement_changes_increment_version_and_reset_approvals(): void
    {
        Storage::fake('contracts');
        [$application, $owner, $applicant, $organization] = $this->application();
        $payload = ['terms_snapshot' => ['rent' => 1800000], 'contract_file' => UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 one')];
        $this->actingAs($owner)->post(route('organizations.applications.agreement.upsert', [$organization, $application]), $payload);
        $oldPath = $application->fresh()->rentalAgreement->contract_storage_path;
        $this->actingAs($owner)->post(route('organizations.applications.agreement.approve', [$organization, $application]));
        $this->actingAs($owner)->post(route('organizations.applications.agreement.upsert', [$organization, $application]), [
            'terms_snapshot' => ['rent' => 1900000],
            'contract_file' => UploadedFile::fake()->createWithContent('replacement.pdf', '%PDF-1.4 two'),
        ])->assertRedirect();
        $agreement = $application->fresh()->rentalAgreement;
        $this->assertSame(2, $agreement->version);
        $this->assertNull($agreement->organization_approved_at);
        $this->assertNull($agreement->applicant_approved_at);
        Storage::disk('contracts')->assertMissing($oldPath);
    }

    public function test_failed_contract_write_is_cleaned_up_and_does_not_create_agreement(): void
    {
        [$application, $owner, , $organization] = $this->application();
        $disk = \Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('contracts')->andReturn($disk);

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($owner)->post(route('organizations.applications.agreement.upsert', [$organization, $application]), [
                'terms_snapshot' => ['rent' => 1800000],
                'contract_file' => UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 contract'),
            ]);
            $this->fail('The failed contract write should throw.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('rental_agreements', 0);
        }
    }

    public function test_booking_expiry_is_snapshotted_from_property_policy(): void
    {
        Storage::fake('contracts');
        [$application, $owner, $applicant, $organization] = $this->application();
        $application->unit->property->update(['booking_expiry_days' => 3]);
        $this->createApprovedAgreement($application, $owner, $applicant, $organization);

        $booking = $application->fresh()->booking;
        $this->assertSame(3, $booking->booking_expiry_days);
        $this->assertEqualsWithDelta(now()->addDays(3)->timestamp, $booking->expires_at->timestamp, 2);
        $application->unit->property->update(['booking_expiry_days' => 10]);
        $this->assertEqualsWithDelta(now()->addDays(3)->timestamp, $booking->fresh()->expires_at->timestamp, 2);
    }

    public function test_expire_command_releases_unit_and_records_expiry(): void
    {
        $booking = Booking::factory()->create(['expires_at' => now()->subMinute()]);
        $booking->unit->update(['status' => UnitStatus::Unavailable]);

        Artisan::call('bookings:expire');

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertSame(UnitStatus::Available, $booking->unit->fresh()->status);
        $this->assertDatabaseCount('tenancies', 0);
        $this->assertDatabaseHas('audit_events', ['action' => 'booking.expired', 'subject_id' => $booking->id]);
    }

    public function test_end_expired_tenancies_marks_tenancy_ended_and_releases_unit(): void
    {
        $tenancy = Tenancy::factory()->create([
            'end_date' => today()->subDay(),
            'status' => 'active',
        ]);
        $tenancy->unit->update(['status' => UnitStatus::Unavailable]);

        Artisan::call('tenancies:end-expired');

        $this->assertSame('ended', $tenancy->fresh()->status->value);
        $this->assertSame(UnitStatus::Available, $tenancy->unit->fresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'tenancy.ended',
            'subject_id' => $tenancy->id,
        ]);
    }

    public function test_due_payment_expires_booking_instead_of_completing_it(): void
    {
        Storage::fake('contracts');
        [$application, $owner, $applicant, $organization] = $this->application();
        $this->createApprovedAgreement($application, $owner, $applicant, $organization);
        $booking = $application->fresh()->booking;
        $booking->update(['expires_at' => now()->subSecond()]);

        $this->actingAs($owner)->post(route('organizations.bookings.confirm-payment', [$organization, $booking]), [
            'amount' => 1800000,
        ])->assertStatus(409);

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertSame(UnitStatus::Available, $booking->unit->fresh()->status);
    }

    /** @return array{RentalApplication, User, User, Organization} */
    private function application(): array
    {
        $owner = User::factory()->create();
        $applicant = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->memberships()->create(['user_id' => $owner->id, 'role' => OrganizationRole::Owner, 'accepted_at' => now()]);
        $property = Property::factory()->create(['organization_id' => $organization->id, 'property_type' => PropertyType::Kost]);
        $unit = Unit::factory()->create(['property_id' => $property->id, 'status' => UnitStatus::Available]);
        $listing = $property->ensureDraftListing();
        $listing->forceFill(['status' => ListingStatus::Approved, 'published_at' => now()])->save();
        $application = RentalApplication::factory()->create(['listing_id' => $listing->id, 'unit_id' => $unit->id, 'applicant_id' => $applicant->id, 'status' => ApplicationStatus::Approved]);

        return [$application, $owner, $applicant, $organization];
    }

    private function createApprovedAgreement(RentalApplication $application, User $owner, User $applicant, Organization $organization): void
    {
        $this->actingAs($owner)->post(route('organizations.applications.agreement.upsert', [$organization, $application]), [
            'terms_snapshot' => ['rent' => 1800000],
            'contract_file' => UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 contract'),
        ]);
        $this->actingAs($owner)->post(route('organizations.applications.agreement.approve', [$organization, $application]));
        $this->actingAs($applicant)->post(route('applications.agreement.approve', $application));
    }
}
