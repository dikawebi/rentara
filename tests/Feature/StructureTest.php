<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceMemberRole;
use App\Models\Block;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class StructureTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceWithRole(User $user, WorkspaceMemberRole $role = WorkspaceMemberRole::Owner): Workspace
    {
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return $workspace;
    }

    private function member(User $user, Workspace $workspace, WorkspaceMemberRole $role): WorkspaceMember
    {
        return WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    private function property(Workspace $workspace, User $owner): Property
    {
        return Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
    }

    private function assign(Workspace $workspace, Property $property, User $user): void
    {
        PropertyAssignment::factory()->create([
            'workspace_id' => $workspace->id,
            'property_id' => $property->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_owner_can_crud_building_happy_path(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.buildings.index', $property))
            ->assertOk()
            ->assertSee('Belum ada gedung');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.store', $property), ['name' => 'Gedung A', 'sort_order' => 1, 'notes' => 'Utama'])
            ->assertRedirect();

        $building = Building::where('property_id', $property->id)->sole();
        $this->assertSame('Gedung A', $building->name);
        $this->assertSame($workspace->id, $building->workspace_id);

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.buildings.index', $property))
            ->assertOk()
            ->assertSee('Gedung A');

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.properties.buildings.update', [$property, $building]), ['name' => 'Gedung B', 'sort_order' => 2])
            ->assertRedirect();
        $this->assertDatabaseHas('buildings', ['id' => $building->id, 'name' => 'Gedung B']);

        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.properties.buildings.destroy', [$property, $building]))
            ->assertRedirect();
        $this->assertSoftDeleted('buildings', ['id' => $building->id]);

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.restore', [$property, $building]))
            ->assertRedirect();
        $this->assertDatabaseHas('buildings', ['id' => $building->id, 'deleted_at' => null]);
    }

    public function test_floor_and_block_can_be_created_and_listed(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.floors.store', $property), ['name' => 'Lantai 1'])
            ->assertRedirect();
        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.blocks.store', $property), ['name' => 'Blok A'])
            ->assertRedirect();

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.floors.index', $property))->assertOk()->assertSee('Lantai 1');
        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.blocks.index', $property))->assertOk()->assertSee('Blok A');
    }

    public function test_duplicate_structure_name_rejected_in_same_property_but_allowed_across_properties(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $first = $this->property($workspace, $owner);
        $second = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.store', $first), ['name' => 'Gedung Sama'])
            ->assertRedirect();

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.store', $first), ['name' => 'Gedung Sama'])
            ->assertSessionHasErrors('name');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.store', $second), ['name' => 'Gedung Sama'])
            ->assertRedirect();
        $this->assertDatabaseHas('buildings', ['property_id' => $second->id, 'name' => 'Gedung Sama']);
    }

    public function test_structure_validation_rejects_bad_input(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.floors.store', $property), ['name' => null])
            ->assertSessionHasErrors('name');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.blocks.store', $property), ['name' => 'Blok X', 'sort_order' => -1])
            ->assertSessionHasErrors('sort_order');

        $this->assertDatabaseCount('floors', 0);
        $this->assertDatabaseCount('blocks', 0);
    }

    public function test_cross_property_structure_access_returns_404(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $other = $this->property($workspace, $owner);
        $building = Building::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $other->id]);
        $session = ['current_workspace_id' => $workspace->id];

        // Building factory does not have created_by; ensure property linkage only.
        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.buildings.edit', [$property, $building->id]))
            ->assertNotFound();
        $this->actingAs($owner)->withSession($session)
            ->put(route('app.properties.buildings.update', [$property, $building->id]), ['name' => 'Ubah'])
            ->assertNotFound();
        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.properties.buildings.destroy', [$property, $building->id]))
            ->assertNotFound();
        $this->assertDatabaseHas('buildings', ['id' => $building->id]);
    }

    public function test_cross_workspace_property_returns_404(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $foreign = Property::factory()->create();
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.buildings.index', $foreign))
            ->assertNotFound();
        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.store', $foreign), ['name' => 'Gedung Asing'])
            ->assertNotFound();
    }

    public function test_structure_restore_conflict_returns_422(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        // The hard unique index normally makes this state unreachable via HTTP;
        // drop it in-test to simulate the race the 422 pre-check guards against.
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'name']);
        });

        $building = Building::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'name' => 'Gedung Konflik']);
        $building->delete();
        Building::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'name' => 'Gedung Konflik']);

        $response = $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.restore', [$property, $building->id]));
        $response->assertStatus(422);
        $this->assertStringContainsString('sudah digunakan', $response->exception?->getMessage() ?? '');
        $this->assertSoftDeleted('buildings', ['id' => $building->id]);
    }

    public function test_structure_role_matrix_assigned_manager_owner_bypass_staff_read_only(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($manager, $workspace, WorkspaceMemberRole::Manager);
        $this->member($staff, $workspace, WorkspaceMemberRole::Staff);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        // Nobody assigned yet: manager and staff are denied.
        $this->actingAs($manager)->withSession($session)->get(route('app.properties.buildings.index', $property))->assertForbidden();
        $this->actingAs($staff)->withSession($session)->get(route('app.properties.buildings.index', $property))->assertForbidden();
        $this->actingAs($manager)->withSession($session)->post(route('app.properties.buildings.store', $property), ['name' => 'Gedung M'])->assertForbidden();

        // Owner bypass: full access without assignment.
        $this->actingAs($owner)->withSession($session)->get(route('app.properties.buildings.index', $property))->assertOk();
        $this->actingAs($owner)->withSession($session)->post(route('app.properties.buildings.store', $property), ['name' => 'Gedung O'])->assertRedirect();

        $this->assign($workspace, $property, $manager);
        $this->assign($workspace, $property, $staff);

        // Assigned manager can manage.
        $this->actingAs($manager)->withSession($session)->get(route('app.properties.buildings.index', $property))->assertOk();
        $this->actingAs($manager)->withSession($session)->post(route('app.properties.buildings.store', $property), ['name' => 'Gedung M'])->assertRedirect();

        // Assigned staff is read-only.
        $this->actingAs($staff)->withSession($session)->get(route('app.properties.buildings.index', $property))->assertOk();
        $this->actingAs($staff)->withSession($session)->post(route('app.properties.buildings.store', $property), ['name' => 'Gedung S'])->assertForbidden();

        $building = Building::where('property_id', $property->id)->where('name', 'Gedung M')->firstOrFail();
        $this->actingAs($staff)->withSession($session)->put(route('app.properties.buildings.update', [$property, $building]), ['name' => 'Gedung S'])->assertForbidden();
        $this->actingAs($staff)->withSession($session)->delete(route('app.properties.buildings.destroy', [$property, $building]))->assertForbidden();
    }

    public function test_super_admin_is_denied_structure_routes(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $property = Property::factory()->create();
        $building = Building::factory()->create();

        $this->actingAs($admin)->get(route('app.properties.buildings.index', $property))->assertForbidden();
        $this->actingAs($admin)->post(route('app.properties.buildings.store', $property), ['name' => 'Gedung X'])->assertForbidden();
        $this->actingAs($admin)->put(route('app.properties.buildings.update', [$property, $building]), ['name' => 'Gedung X'])->assertForbidden();
    }

    public function test_trashed_structure_name_reuse_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.store', $property), ['name' => 'Gedung Arsip'])
            ->assertRedirect();

        $building = Building::where('property_id', $property->id)->sole();
        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.properties.buildings.destroy', [$property, $building]))
            ->assertRedirect();

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.store', $property), ['name' => 'Gedung Arsip'])
            ->assertSessionHasErrors('name');
        $this->assertSame(1, Building::withTrashed()->where('property_id', $property->id)->count());
    }

    public function test_update_without_sort_order_preserves_existing_value(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.buildings.store', $property), ['name' => 'Gedung Urut', 'sort_order' => 7])
            ->assertRedirect();
        $building = Building::where('property_id', $property->id)->sole();
        $this->assertSame(7, $building->sort_order);

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.properties.buildings.update', [$property, $building]), ['name' => 'Gedung Urut Baru'])
            ->assertRedirect();
        $this->assertDatabaseHas('buildings', ['id' => $building->id, 'name' => 'Gedung Urut Baru', 'sort_order' => 7]);
    }
}
