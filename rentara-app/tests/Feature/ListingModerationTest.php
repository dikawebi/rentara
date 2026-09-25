<?php

namespace Tests\Feature;

use App\ListingStatus;
use App\Models\Listing;
use App\Models\Organization;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\Unit;
use App\Models\User;
use App\OrganizationRole;
use App\PropertyType;
use App\UnitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ListingModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_is_private_while_pending_and_public_only_after_admin_approval(): void
    {
        Storage::fake('property_photos');
        [$owner, $organization, $property, $listing, $photo] = $this->createReviewableListing();

        $this->actingAs($owner)
            ->post(route('organizations.properties.listing.submit', [$organization, $property]))
            ->assertSessionHas('status', 'listing-submitted');

        $this->assertSame(ListingStatus::PendingReview, $listing->fresh()->status);
        $this->assertSame($owner->id, $listing->fresh()->submitted_by);
        $this->get('/listings')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Listings/Index')
                ->where('listings.total', 0));
        $this->get(route('listings.show', $listing->slug))->assertNotFound();
        $this->get(route('listings.photos.show', [$listing->slug, $photo, 'full']))->assertNotFound();

        $admin = $this->createPlatformAdmin();
        $this->actingAs($admin)
            ->get('/admin/listings')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Listings/Index')
                ->where('listings.total', 1));
        $this->get(route('admin.listings.show', $listing->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Listings/Show')
                ->has('photos', 1));
        $this->post(route('admin.listings.approve', $listing->slug))
            ->assertRedirect(route('admin.listings.index'));

        $this->assertSame(ListingStatus::Approved, $listing->fresh()->status);
        $this->assertSame($admin->id, $listing->fresh()->reviewed_by);
        $this->get('/listings')
            ->assertOk()
            ->assertSee('"total":1', false)
            ->assertSee($property->name, false)
            ->assertDontSee($owner->email, false);
        $this->get('/listings?property_type=kontrakan')->assertSee('"total":0', false);
        $this->get('/listings?district=Tebet&max_price=2000000')->assertSee('"total":1', false);
        $this->get(route('listings.show', $listing->slug))
            ->assertOk()
            ->assertSee($property->full_address, false)
            ->assertSee($listing->slug, false)
            ->assertDontSee($owner->email, false);
        $this->get(route('listings.photos.show', [$listing->slug, $photo, 'full']))->assertOk();

        $this->actingAs($owner)
            ->post(route('organizations.properties.listing.pause', [$organization, $property]))
            ->assertSessionHas('status', 'listing-paused');

        $this->assertSame(ListingStatus::Paused, $listing->fresh()->status);
        $this->get(route('listings.show', $listing->slug))->assertNotFound();
        $this->get(route('listings.photos.show', [$listing->slug, $photo, 'full']))->assertNotFound();
    }

    public function test_owner_edits_return_an_approved_listing_to_draft_and_unpublish_it(): void
    {
        [$owner, $organization, $property, $listing] = $this->createReviewableListing();
        $listing->forceFill([
            'status' => ListingStatus::Approved,
            'published_at' => now(),
            'reviewed_at' => now(),
        ])->save();

        $this->actingAs($owner)
            ->patch(route('organizations.properties.update', [$organization, $property]), [
                'name' => 'Nama Properti yang Berubah',
                'property_type' => PropertyType::Kost->value,
                'description' => 'Deskripsi baru.',
                'full_address' => 'Alamat terbaru, Jakarta',
                'district' => 'Tebet',
                'city' => 'Jakarta Selatan',
            ])
            ->assertSessionHas('status', 'property-updated');

        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
        $this->assertNull($listing->fresh()->published_at);
        $this->get('/listings')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('listings.total', 0));
        $this->get(route('listings.show', $listing->slug))->assertNotFound();
    }

    public function test_admin_rejection_requires_actionable_notes_and_stays_private(): void
    {
        [$owner, $organization, $property, $listing] = $this->createReviewableListing();
        $this->actingAs($owner)->post(route('organizations.properties.listing.submit', [$organization, $property]));
        $admin = $this->createPlatformAdmin();

        $this->actingAs($admin)
            ->post(route('admin.listings.reject', $listing->slug), ['review_notes' => 'short'])
            ->assertSessionHasErrors('review_notes');

        $this->post(route('admin.listings.reject', $listing->slug), [
            'review_notes' => 'Mohon lengkapi keterangan fasilitas properti.',
        ])->assertRedirect(route('admin.listings.index'));

        $this->assertSame(ListingStatus::Rejected, $listing->fresh()->status);
        $this->assertSame('Mohon lengkapi keterangan fasilitas properti.', $listing->fresh()->rejection_reason);
        $this->get('/listings')->assertInertia(fn (AssertableInertia $page) => $page->where('listings.total', 0));
    }

    public function test_non_admin_cannot_open_moderation_queue_or_approve_a_listing(): void
    {
        [$owner, $organization, $property, $listing] = $this->createReviewableListing();
        $this->actingAs($owner)->post(route('organizations.properties.listing.submit', [$organization, $property]));

        $this->get('/admin/listings')->assertForbidden();
        $this->post(route('admin.listings.approve', $listing->slug))->assertForbidden();
        $this->assertSame(ListingStatus::PendingReview, $listing->fresh()->status);
    }

    public function test_listing_requires_an_available_unit_and_photo_for_submission_and_approval(): void
    {
        [$owner, $organization, $property] = $this->createReviewableListing();
        $property->photos()->delete();
        $listing = $property->listing()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('organizations.properties.listing.submit', [$organization, $property]))
            ->assertSessionHasErrors('listing');

        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);

        $listing->forceFill(['status' => ListingStatus::PendingReview])->save();
        $admin = $this->createPlatformAdmin();

        $this->actingAs($admin)
            ->post(route('admin.listings.approve', $listing->slug))
            ->assertSessionHasErrors('listing');

        $this->assertSame(ListingStatus::PendingReview, $listing->fresh()->status);
        $this->get(route('listings.show', $listing->slug))->assertNotFound();
    }

    /** @return array{User, Organization, Property, Listing, PropertyPhoto} */
    private function createReviewableListing(): array
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
            'name' => 'Kos Review Tebet',
            'property_type' => PropertyType::Kost,
            'full_address' => 'Jl. Tebet Barat, Jakarta Selatan',
            'district' => 'Tebet',
            'city' => 'Jakarta Selatan',
        ]);
        Unit::factory()->create([
            'property_id' => $property->id,
            'status' => UnitStatus::Available,
            'monthly_price' => 1800000,
        ]);
        $listing = $property->ensureDraftListing();
        $photo = PropertyPhoto::factory()->create([
            'property_id' => $property->id,
            'uploaded_by' => $owner->id,
        ]);
        Storage::disk('property_photos')->put($photo->path, 'private-full-image');
        Storage::disk('property_photos')->put($photo->thumbnail_path, 'private-thumbnail');

        return [$owner, $organization, $property, $listing, $photo];
    }

    private function createPlatformAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        return $admin;
    }
}
