<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use App\OrganizationRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PropertyPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_upload_processed_photos_and_read_them_through_private_routes(): void
    {
        Storage::fake('property_photos');
        [$manager, $organization, $property] = $this->createPropertyWorkspace();
        $upload = UploadedFile::fake()->image('living-room.png', 800, 600)->size(1024);

        $this->actingAs($manager)
            ->post(route('organizations.properties.photos.store', [$organization, $property]), [
                'photos' => [$upload],
            ])
            ->assertSessionHas('status', 'property-photos-uploaded');

        $photo = PropertyPhoto::query()->firstOrFail();

        $this->assertStringEndsWith('.webp', $photo->path);
        $this->assertStringEndsWith('.webp', $photo->thumbnail_path);
        $this->assertStringNotContainsString('living-room', $photo->path);
        Storage::disk('property_photos')->assertExists([$photo->path, $photo->thumbnail_path]);

        $this->get(route('organizations.properties.photos.show', [$organization, $property, $photo, 'thumbnail']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($manager)
            ->get(route('organizations.properties.show', [$organization, $property]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('photos', 1)
                ->where('photos.0.thumbnail_url', route('organizations.properties.photos.show', [$organization, $property, $photo, 'thumbnail']))
                ->missing('photos.0.path'));
    }

    public function test_invalid_uploads_are_rejected_without_writing_files(): void
    {
        Storage::fake('property_photos');
        [$manager, $organization, $property] = $this->createPropertyWorkspace();

        $this->actingAs($manager)
            ->post(route('organizations.properties.photos.store', [$organization, $property]), [
                'photos' => [
                    UploadedFile::fake()->create('not-an-image.txt', 100, 'text/plain'),
                    UploadedFile::fake()->image('too-large.png', 800, 600)->size(5121),
                ],
            ])
            ->assertSessionHasErrors(['photos.0', 'photos.1']);

        $this->assertDatabaseCount('property_photos', 0);
        Storage::disk('property_photos')->assertEmpty();
    }

    public function test_photo_access_and_upload_are_scoped_to_organization_members(): void
    {
        Storage::fake('property_photos');
        [$manager, $organization, $property] = $this->createPropertyWorkspace();
        $photo = PropertyPhoto::factory()->create([
            'property_id' => $property->id,
            'uploaded_by' => $manager->id,
        ]);
        Storage::disk('property_photos')->put($photo->path, 'private-image');
        Storage::disk('property_photos')->put($photo->thumbnail_path, 'private-thumbnail');

        $outsider = User::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $otherOrganization->memberships()->create([
            'user_id' => $outsider->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);

        $this->actingAs($outsider)
            ->get(route('organizations.properties.photos.show', [$otherOrganization, $property, $photo, 'full']))
            ->assertNotFound();

        $staff = User::factory()->create();
        $organization->memberships()->create([
            'user_id' => $staff->id,
            'role' => OrganizationRole::Staff,
            'accepted_at' => now(),
        ]);

        $this->actingAs($staff)
            ->post(route('organizations.properties.photos.store', [$organization, $property]), [
                'photos' => [UploadedFile::fake()->image('room.png', 800, 600)],
            ])
            ->assertForbidden();

        $this->assertCount(2, Storage::disk('property_photos')->allFiles());
    }

    public function test_photos_can_be_reordered_and_removed_with_their_private_files(): void
    {
        Storage::fake('property_photos');
        [$manager, $organization, $property] = $this->createPropertyWorkspace();

        $this->actingAs($manager)
            ->post(route('organizations.properties.photos.store', [$organization, $property]), [
                'photos' => [
                    UploadedFile::fake()->image('one.png', 800, 600),
                    UploadedFile::fake()->image('two.png', 800, 600),
                ],
            ]);

        $photos = $property->photos()->orderBy('position')->get();
        $first = $photos[0];
        $second = $photos[1];

        $this->patch(route('organizations.properties.photos.reorder', [$organization, $property]), [
            'photo_ids' => [$second->id, $first->id],
        ])->assertSessionHas('status', 'property-photos-reordered');

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);

        $this->delete(route('organizations.properties.photos.destroy', [$organization, $property, $second]))
            ->assertSessionHas('status', 'property-photo-deleted');

        $this->assertDatabaseMissing('property_photos', ['id' => $second->id]);
        Storage::disk('property_photos')->assertMissing([$second->path, $second->thumbnail_path]);
        $this->assertSame(0, $first->fresh()->position);
    }

    /** @return array{User, Organization, Property} */
    private function createPropertyWorkspace(): array
    {
        $manager = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->memberships()->create([
            'user_id' => $manager->id,
            'role' => OrganizationRole::Manager,
            'accepted_at' => now(),
        ]);

        $property = Property::factory()->create(['organization_id' => $organization->id]);

        return [$manager, $organization, $property];
    }
}
