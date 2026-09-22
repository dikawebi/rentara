<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceMemberRole;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PropertyTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kost Mawar Indah',
            'property_type' => 'kost',
            'status' => 'active',
            'address' => 'Jl. Melati No. 10',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40111',
            'latitude' => '-6.9000000',
            'longitude' => '107.6000000',
            'phone' => '081234567890',
            'email' => 'kost@example.test',
        ], $overrides);
    }

    public function test_owner_can_crud_property_happy_path(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.store'), $this->payload())
            ->assertRedirect();

        $property = Property::where('workspace_id', $workspace->id)->sole();
        $this->assertSame($owner->id, $property->created_by);

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.index'))
            ->assertOk()
            ->assertSee('Kost Mawar Indah')
            ->assertSee('Aktif');

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.show', $property))
            ->assertOk()
            ->assertSee('Kost Mawar Indah');

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.properties.update', $property), $this->payload(['name' => 'Kost Melati Indah', 'status' => 'inactive']))
            ->assertRedirect(route('app.properties.show', $property));

        $this->assertDatabaseHas('properties', ['id' => $property->id, 'name' => 'Kost Melati Indah', 'status' => 'inactive']);

        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.properties.destroy', $property))
            ->assertRedirect(route('app.properties.index'));

        $this->assertSoftDeleted('properties', ['id' => $property->id]);
    }

    public function test_created_by_is_set_server_side_and_ignores_client_input(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('app.properties.store'), $this->payload(['created_by' => $other->id, 'workspace_id' => 999999]));

        $property = Property::where('workspace_id', $workspace->id)->sole();
        $this->assertSame($owner->id, $property->created_by);
        $this->assertSame($workspace->id, $property->workspace_id);
    }

    public function test_validation_rejects_missing_name_invalid_enums_and_bad_coordinates(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.store'), $this->payload(['name' => null]))
            ->assertSessionHasErrors('name');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.store'), $this->payload(['property_type' => 'villa']))
            ->assertSessionHasErrors('property_type');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.store'), $this->payload(['status' => 'sold']))
            ->assertSessionHasErrors('status');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.store'), $this->payload(['latitude' => 'not-a-number', 'longitude' => 500]))
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.store'), $this->payload(['email' => 'bukan-surel']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('properties', 0);
    }

    public function test_duplicate_name_is_rejected_in_same_workspace_but_allowed_across_workspaces(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $other = Workspace::factory()->create();
        $this->member($owner, $other, WorkspaceMemberRole::Owner);
        Property::factory()->create(['workspace_id' => $other->id, 'name' => 'Kost Sama']);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('app.properties.store'), $this->payload(['name' => 'Kost Sama']))
            ->assertRedirect();
        $this->assertDatabaseHas('properties', ['workspace_id' => $workspace->id, 'name' => 'Kost Sama']);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('app.properties.store'), $this->payload(['name' => 'Kost Sama']))
            ->assertSessionHasErrors('name');
    }

    public function test_update_rejects_duplicate_name_but_allows_keeping_own_name(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $session = ['current_workspace_id' => $workspace->id];
        $first = Property::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Kost A', 'created_by' => $owner->id]);
        $second = Property::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Kost B', 'created_by' => $owner->id]);

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.properties.update', $second), $this->payload(['name' => 'Kost A']))
            ->assertSessionHasErrors('name');

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.properties.update', $second), $this->payload(['name' => 'Kost B']))
            ->assertRedirect();
    }

    public function test_cross_workspace_property_access_returns_404(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $foreign = Property::factory()->create();
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)->get(route('app.properties.show', $foreign))->assertNotFound();
        $this->actingAs($owner)->withSession($session)->get(route('app.properties.edit', $foreign))->assertNotFound();
        $this->actingAs($owner)->withSession($session)->put(route('app.properties.update', $foreign), $this->payload())->assertNotFound();
        $this->actingAs($owner)->withSession($session)->delete(route('app.properties.destroy', $foreign))->assertNotFound();
        $this->assertDatabaseHas('properties', ['id' => $foreign->id]);
    }

    public function test_role_matrix_owner_full_manager_no_delete_staff_read_only(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($manager, $workspace, WorkspaceMemberRole::Manager);
        $this->member($staff, $workspace, WorkspaceMemberRole::Staff);
        $property = Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $manager->id]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $staff->id]);

        // Staff: read-only.
        $this->actingAs($staff)->withSession(['current_workspace_id' => $workspace->id])->get(route('app.properties.index'))->assertOk();
        $this->actingAs($staff)->withSession(['current_workspace_id' => $workspace->id])->get(route('app.properties.show', $property))->assertOk();
        $this->actingAs($staff)->withSession(['current_workspace_id' => $workspace->id])->get(route('app.properties.create'))->assertForbidden();
        $this->actingAs($staff)->withSession(['current_workspace_id' => $workspace->id])->post(route('app.properties.store'), $this->payload())->assertForbidden();
        $this->actingAs($staff)->withSession(['current_workspace_id' => $workspace->id])->get(route('app.properties.edit', $property))->assertForbidden();
        $this->actingAs($staff)->withSession(['current_workspace_id' => $workspace->id])->put(route('app.properties.update', $property), $this->payload())->assertForbidden();
        $this->actingAs($staff)->withSession(['current_workspace_id' => $workspace->id])->delete(route('app.properties.destroy', $property))->assertForbidden();

        // Manager: create/update, no delete.
        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])->get(route('app.properties.create'))->assertOk();
        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])->post(route('app.properties.store'), $this->payload(['name' => 'Kost Manajer']))->assertRedirect();
        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])->put(route('app.properties.update', $property), $this->payload(['name' => 'Kost Diubah Manajer']))->assertRedirect();
        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])->delete(route('app.properties.destroy', $property))->assertForbidden();
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'deleted_at' => null]);
    }

    public function test_owner_can_restore_soft_deleted_property_but_manager_cannot(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($manager, $workspace, WorkspaceMemberRole::Manager);
        $property = Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->delete(route('app.properties.destroy', $property))
            ->assertRedirect();
        $this->assertSoftDeleted('properties', ['id' => $property->id]);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('app.properties.show', $property))
            ->assertNotFound();

        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('app.properties.restore', $property))
            ->assertForbidden();
        $this->assertSoftDeleted('properties', ['id' => $property->id]);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('app.properties.restore', $property))
            ->assertRedirect();
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'deleted_at' => null]);
    }

    public function test_super_admin_is_denied_property_routes(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $property = Property::factory()->create();

        $this->actingAs($admin)->get(route('app.properties.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('app.properties.show', $property))->assertForbidden();
        $this->actingAs($admin)->post(route('app.properties.store'), $this->payload())->assertForbidden();
    }

    public function test_guest_and_unverified_users_cannot_access_property_routes(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);

        $this->get(route('app.properties.index'))->assertRedirect('/login');
        $this->get(route('app.properties.show', $property))->assertRedirect('/login');

        $unverified = User::factory()->unverified()->create();
        $this->member($unverified, $workspace, WorkspaceMemberRole::Owner);

        $this->actingAs($unverified)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('app.properties.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_index_shows_empty_state_when_no_properties(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('app.properties.index'))
            ->assertOk()
            ->assertSee('Belum ada properti');
    }

    public function test_trashed_name_reuse_is_rejected_with_indonesian_message(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.store'), $this->payload(['name' => 'Kost Arsip']))
            ->assertRedirect();

        $property = Property::where('workspace_id', $workspace->id)->sole();
        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.properties.destroy', $property))
            ->assertRedirect();
        $this->assertSoftDeleted('properties', ['id' => $property->id]);

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.store'), $this->payload(['name' => 'Kost Arsip']))
            ->assertSessionHasErrors('name');

        $errors = session('errors');
        $this->assertStringContainsString('telah dihapus', (string) $errors->get('name')[0]);
        $this->assertSame(1, Property::withTrashed()->where('workspace_id', $workspace->id)->count());
    }

    public function test_property_restore_conflict_returns_422(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $session = ['current_workspace_id' => $workspace->id];

        Schema::table('properties', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'name']);
        });

        $property = Property::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Kost Konflik', 'created_by' => $owner->id]);
        $property->delete();
        Property::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Kost Konflik', 'created_by' => $owner->id]);

        $response = $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.restore', $property));
        $response->assertStatus(422);
        $this->assertStringContainsString('sudah digunakan', $response->exception?->getMessage() ?? '');
        $this->assertSoftDeleted('properties', ['id' => $property->id]);
    }

    public function test_index_scoping_unassigned_staff_sees_none_owner_sees_all(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($staff, $workspace, WorkspaceMemberRole::Staff);
        $assigned = Property::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Kost Ditugaskan', 'created_by' => $owner->id]);
        $unassigned = Property::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Kost Tak Ditugaskan', 'created_by' => $owner->id]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $assigned->id, 'user_id' => $staff->id]);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($staff)->withSession($session)
            ->get(route('app.properties.index'))
            ->assertOk()
            ->assertSee('Kost Ditugaskan')
            ->assertDontSee('Kost Tak Ditugaskan');

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.index'))
            ->assertOk()
            ->assertSee('Kost Ditugaskan')
            ->assertSee('Kost Tak Ditugaskan');
    }
}
