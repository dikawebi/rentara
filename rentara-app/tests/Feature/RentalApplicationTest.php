<?php

namespace Tests\Feature;

use App\ApplicationStatus;
use App\ListingStatus;
use App\Models\Listing;
use App\Models\Organization;
use App\Models\Property;
use App\Models\RentalApplication;
use App\Models\Unit;
use App\Models\User;
use App\OrganizationRole;
use App\PropertyType;
use App\UnitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RentalApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_applicant_can_submit_and_view_an_application_with_consent_snapshot(): void
    {
        [$listing, $unit] = $this->createApprovedListing();
        $applicant = User::factory()->create([
            'name' => 'Ayu Applicant',
            'email' => 'ayu@example.test',
        ]);

        $this->actingAs($applicant)
            ->get(route('listings.apply', $listing->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Applications/Create')
                ->where('units.0.id', $unit->id));

        $this->post(route('listings.applications.store', $listing->slug), [
            'unit_id' => $unit->id,
            'requested_move_in' => now()->addWeeks(3)->toDateString(),
            'requested_duration_months' => 12,
            'applicant_note' => 'Saya bekerja di area Tebet.',
            'privacy_consent' => true,
            'applicant_id' => 99999,
            'status' => 'approved',
        ])->assertRedirect();

        $application = RentalApplication::query()->firstOrFail();
        $this->assertSame($applicant->id, $application->applicant_id);
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame(config('rentara.privacy_notice_version'), $application->privacy_notice_version);
        $this->assertNotNull($application->privacy_accepted_at);
        $this->assertSame('Ayu Applicant', $application->applicant_snapshot['name']);
        $this->assertSame('ayu@example.test', $application->applicant_snapshot['email']);
        $this->assertSame($applicant->id.':'.$unit->id, $application->active_application_key);
        $this->assertSame('Kos Application', $application->listing_snapshot['property_name']);
        $this->assertSame($unit->monthly_price, $application->listing_snapshot['monthly_price']);

        $this->get(route('applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Applications/Show')
                ->where('application.status', 'submitted')
                ->where('application.applicant_note', 'Saya bekerja di area Tebet.'));
        $this->get('/my-applications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Applications/Index')
                ->has('applications.data', 1));
    }

    public function test_one_active_application_per_applicant_unit_and_privacy_consent_are_enforced(): void
    {
        [$listing, $unit] = $this->createApprovedListing();
        $applicant = User::factory()->create();
        $payload = [
            'unit_id' => $unit->id,
            'requested_move_in' => now()->addWeeks(2)->toDateString(),
            'requested_duration_months' => 6,
            'privacy_consent' => true,
        ];

        $this->actingAs($applicant)->post(route('listings.applications.store', $listing->slug), $payload)
            ->assertRedirect();

        $this->post(route('listings.applications.store', $listing->slug), $payload)
            ->assertSessionHasErrors('unit_id');

        $this->assertDatabaseCount('rental_applications', 1);

        $otherApplicant = User::factory()->create();
        $this->actingAs($otherApplicant)
            ->post(route('listings.applications.store', $listing->slug), [
                ...$payload,
                'privacy_consent' => false,
            ])
            ->assertSessionHasErrors('privacy_consent');

        $this->assertDatabaseCount('rental_applications', 1);
    }

    public function test_applicant_cannot_apply_to_another_listing_property_unit(): void
    {
        [$listing] = $this->createApprovedListing();
        [, $otherUnit] = $this->createApprovedListing();
        $applicant = User::factory()->create();

        $this->actingAs($applicant)
            ->post(route('listings.applications.store', $listing->slug), [
                'unit_id' => $otherUnit->id,
                'requested_move_in' => now()->addWeeks(2)->toDateString(),
                'requested_duration_months' => 6,
                'privacy_consent' => true,
            ])
            ->assertSessionHasErrors('unit_id');

        $this->assertDatabaseCount('rental_applications', 0);
    }

    public function test_applications_require_a_verified_account(): void
    {
        [$listing, $unit] = $this->createApprovedListing();
        $payload = [
            'unit_id' => $unit->id,
            'requested_move_in' => now()->addWeeks(2)->toDateString(),
            'requested_duration_months' => 6,
            'privacy_consent' => true,
        ];

        $this->get(route('listings.apply', $listing->slug))->assertRedirect(route('login'));

        $unverifiedApplicant = User::factory()->unverified()->create();
        $this->actingAs($unverifiedApplicant)
            ->get(route('listings.apply', $listing->slug))
            ->assertRedirect(route('verification.notice'));

        $this->post(route('listings.applications.store', $listing->slug), $payload)
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('rental_applications', 0);
    }

    public function test_only_the_applicant_can_view_an_application(): void
    {
        [$listing, $unit] = $this->createApprovedListing();
        $applicant = User::factory()->create();
        $application = RentalApplication::query()->create([
            'listing_id' => $listing->id,
            'applicant_id' => $applicant->id,
            'applicant_snapshot' => ['name' => $applicant->name, 'email' => $applicant->email],
            'unit_id' => $unit->id,
            'requested_move_in' => now()->addWeeks(2)->toDateString(),
            'requested_duration_months' => 12,
            'privacy_notice_version' => config('rentara.privacy_notice_version'),
            'privacy_accepted_at' => now(),
            'status' => ApplicationStatus::Submitted,
            'listing_snapshot' => ['property_name' => 'Snapshot', 'unit_name' => 'A1'],
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('applications.show', $application))
            ->assertNotFound();

        $this->assertDatabaseCount('rental_applications', 1);
    }

    public function test_another_organization_cannot_view_or_change_an_application(): void
    {
        [$listing, $unit] = $this->createApprovedListing();
        $application = RentalApplication::factory()->create([
            'listing_id' => $listing->id,
            'unit_id' => $unit->id,
            'status' => ApplicationStatus::Submitted,
        ]);
        $otherOwner = User::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $otherOrganization->memberships()->create([
            'user_id' => $otherOwner->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);

        $this->actingAs($otherOwner)
            ->get(route('organizations.applications.show', [$otherOrganization, $application]))
            ->assertNotFound();

        $this->actingAs($otherOwner)
            ->patch(route('organizations.applications.status.update', [$otherOrganization, $application]), [
                'status' => ApplicationStatus::Approved->value,
            ])
            ->assertForbidden();

        $this->assertSame(ApplicationStatus::Submitted, $application->fresh()->status);
    }

    /** @return array{Listing, Unit} */
    private function createApprovedListing(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->memberships()->create([
            'user_id' => $owner->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);

        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Kos Application',
            'property_type' => PropertyType::Kost,
        ]);
        $unit = Unit::factory()->create([
            'property_id' => $property->id,
            'name' => 'Kamar A1',
            'monthly_price' => 1800000,
            'status' => UnitStatus::Available,
        ]);
        $listing = $property->ensureDraftListing();
        $listing->forceFill([
            'status' => ListingStatus::Approved,
            'published_at' => now(),
        ])->save();

        return [$listing, $unit];
    }
}
