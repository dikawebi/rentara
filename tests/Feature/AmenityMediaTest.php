<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceMemberRole;
use App\Models\Amenity;
use App\Models\Media;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AmenityMediaTest extends TestCase
{
    use RefreshDatabase;

    private function member(Workspace $workspace, User $user, WorkspaceMemberRole $role): void
    {
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role]);
    }

    private function property(Workspace $workspace, User $owner): Property
    {
        return Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
    }

    public function test_owner_can_crud_restore_and_sync_workspace_amenities(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $property = $this->property($workspace, $owner);
        $unit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)->post(route('app.amenities.store'), ['name' => 'Wi-Fi'])->assertRedirect();
        $amenity = Amenity::where('workspace_id', $workspace->id)->sole();
        $this->actingAs($owner)->withSession($session)->put(route('app.amenities.update', $amenity), ['name' => 'Wi-Fi cepat'])->assertRedirect();
        $this->actingAs($owner)->withSession($session)->put(route('app.properties.units.amenities.update', [$property, $unit]), ['amenity_ids' => [$amenity->id]])->assertRedirect();
        $this->assertDatabaseHas('amenity_unit', ['amenity_id' => $amenity->id, 'unit_id' => $unit->id, 'workspace_id' => $workspace->id]);
        $this->actingAs($owner)->withSession($session)->delete(route('app.amenities.destroy', $amenity))->assertRedirect();
        $this->assertSoftDeleted('amenities', ['id' => $amenity->id]);
        $this->actingAs($owner)->withSession($session)->post(route('app.amenities.restore', $amenity))->assertRedirect();
        $this->assertDatabaseHas('amenities', ['id' => $amenity->id, 'deleted_at' => null]);
    }

    public function test_amenity_unit_rejects_cross_workspace_pivots(): void
    {
        $workspace = Workspace::factory()->create();
        $foreignWorkspace = Workspace::factory()->create();
        $amenity = Amenity::factory()->create(['workspace_id' => $foreignWorkspace->id]);
        $unit = Unit::factory()->create(['workspace_id' => $workspace->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('amenity_unit')->insert([
            'workspace_id' => $workspace->id,
            'amenity_id' => $amenity->id,
            'unit_id' => $unit->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_staff_reads_but_cannot_write_amenities_and_super_admin_is_denied(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $this->member($workspace, $staff, WorkspaceMemberRole::Staff);
        $amenity = Amenity::factory()->create(['workspace_id' => $workspace->id]);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($staff)->withSession($session)->get(route('app.amenities.index'))->assertOk();
        $this->actingAs($staff)->withSession($session)->post(route('app.amenities.store'), ['name' => 'Baru'])->assertForbidden();
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->actingAs($admin)->withSession($session)->get(route('app.amenities.index'))->assertForbidden();
        $this->assertDatabaseHas('amenities', ['id' => $amenity->id]);
    }

    public function test_assigned_staff_can_read_media_but_only_assigned_manager_can_write(): void
    {
        Storage::fake('local');
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $this->member($workspace, $manager, WorkspaceMemberRole::Manager);
        $this->member($workspace, $staff, WorkspaceMemberRole::Staff);
        $property = $this->property($workspace, $owner);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $manager->id]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $staff->id]);
        $session = ['current_workspace_id' => $workspace->id];

        $response = $this->actingAs($manager)->withSession($session)->post(route('app.properties.media.store', $property), ['file' => UploadedFile::fake()->image('room.jpg')->size(100), 'caption' => 'Ruang']);
        $response->assertRedirect();
        $media = $property->media()->sole();
        $this->assertStringNotContainsString('room.jpg', $media->path);
        $this->actingAs($staff)->withSession($session)->get(route('app.media.stream', $media))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($staff)->withSession($session)->delete(route('app.media.destroy', $media))->assertForbidden();
        $this->actingAs($manager)->withSession($session)->delete(route('app.media.destroy', $media))->assertRedirect();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_media_rejects_non_images_and_cross_workspace_parent(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $property = $this->property($workspace, $owner);
        $foreign = Property::factory()->create();
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)->post(route('app.properties.media.store', $property), ['file' => UploadedFile::fake()->create('bad.txt', 10, 'text/plain')])->assertSessionHasErrors('file');
        $this->actingAs($owner)->withSession($session)->post(route('app.properties.media.store', $foreign), ['file' => UploadedFile::fake()->image('room.jpg')])->assertNotFound();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_upload_failure_does_not_create_a_media_row(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $property = $this->property($workspace, $owner);
        $disk = Mockery::mock();
        $disk->shouldReceive('putFileAs')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('app.properties.media.store', $property), ['file' => UploadedFile::fake()->image('room.jpg')])->assertStatus(500);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_media_deletion_removes_row_before_reporting_disk_failure(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $property = $this->property($workspace, $owner);
        $media = Media::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
        $disk = Mockery::mock();
        $disk->shouldReceive('delete')->once()->with($media->path)->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])->delete(route('app.media.destroy', $media))->assertStatus(500);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_stream_rejects_tampered_path_and_inactive_lifecycle_parents(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $property = $this->property($workspace, $owner);
        $media = Media::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'path' => '../outside.jpg']);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)->get(route('app.media.stream', $media))->assertNotFound();
        $property->update(['status' => 'archived']);
        $media->setRawAttributes(array_merge($media->getAttributes(), ['path' => 'workspaces/'.$workspace->id.'/properties/'.$property->id.'/media/'.\Illuminate\Support\Str::uuid().'.jpg']));
        $media->save();
        $this->actingAs($owner)->withSession($session)->get(route('app.media.stream', $media))->assertForbidden();
    }

    public function test_stream_rejects_cross_workspace_and_parent_path_tampering(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $property = $this->property($workspace, $owner);
        $foreignWorkspace = Workspace::factory()->create();
        $foreignProperty = $this->property($foreignWorkspace, User::factory()->create());
        $media = Media::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
        $session = ['current_workspace_id' => $workspace->id];

        foreach ([
            'workspaces/'.$foreignWorkspace->id.'/properties/'.$property->id.'/media/'.\Illuminate\Support\Str::uuid().'.jpg',
            'workspaces/'.$workspace->id.'/properties/'.$foreignProperty->id.'/media/'.\Illuminate\Support\Str::uuid().'.jpg',
        ] as $path) {
            $media->setRawAttributes(array_merge($media->getAttributes(), ['path' => $path]));
            $media->save();
            $this->actingAs($owner)->withSession($session)->get(route('app.media.stream', $media))->assertNotFound();
        }
    }

    public function test_unit_media_requires_the_unit_parent_and_database_xor(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $property = $this->property($workspace, $owner);
        $unit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'workspace_id' => $workspace->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('media')->insert(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'unit_id' => $unit->id, 'disk' => 'local', 'path' => 'bad', 'original_name' => 'bad', 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size_bytes' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_restore_conflict_returns_unprocessable_entity(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->member($workspace, $owner, WorkspaceMemberRole::Owner);
        $deleted = Amenity::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Parking']);
        $deleted->delete();
        Schema::table('amenities', fn ($table) => $table->dropUnique(['workspace_id', 'name']));
        Amenity::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Parking']);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])->post(route('app.amenities.restore', $deleted))->assertStatus(422);
    }
}
