<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\BackfillPropertyAssignments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
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

    private function member(User $user, Workspace $workspace, WorkspaceMemberRole $role, UserStatus $status = UserStatus::Active): WorkspaceMember
    {
        return WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function property(Workspace $workspace, User $owner): Property
    {
        return Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
    }

    private function assign(Workspace $workspace, Property $property, User $user): PropertyAssignment
    {
        return PropertyAssignment::factory()->create([
            'workspace_id' => $workspace->id,
            'property_id' => $property->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_owner_can_list_assign_and_remove_assignment(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $this->member($manager, $workspace, WorkspaceMemberRole::Manager);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.assignments.index', $property))
            ->assertOk()
            ->assertSee('Belum ada penugasan');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.assignments.store', $property), ['user_id' => $manager->id])
            ->assertRedirect();

        $assignment = PropertyAssignment::where('property_id', $property->id)->sole();
        $this->assertSame($manager->id, $assignment->user_id);
        $this->assertSame($owner->id, $assignment->created_by);

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.assignments.index', $property))
            ->assertOk()
            ->assertSee($manager->name);

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.assignments.store', $property), ['user_id' => $manager->id])
            ->assertSessionHasErrors('user_id');
        $this->assertSame(1, PropertyAssignment::where('property_id', $property->id)->count());

        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.properties.assignments.destroy', [$property, $assignment]))
            ->assertRedirect();
        $this->assertDatabaseMissing('property_assignments', ['id' => $assignment->id]);
    }

    public function test_assignment_rejects_owner_suspended_non_member_and_super_admin(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $secondOwner = User::factory()->create();
        $this->member($secondOwner, $workspace, WorkspaceMemberRole::Owner);
        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.assignments.store', $property), ['user_id' => $secondOwner->id])
            ->assertSessionHasErrors('user_id');

        $suspended = User::factory()->create();
        $this->member($suspended, $workspace, WorkspaceMemberRole::Manager, UserStatus::Suspended);
        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.assignments.store', $property), ['user_id' => $suspended->id])
            ->assertSessionHasErrors('user_id');

        $outsider = User::factory()->create();
        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.assignments.store', $property), ['user_id' => $outsider->id])
            ->assertSessionHasErrors('user_id');

        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->member($admin, $workspace, WorkspaceMemberRole::Manager);
        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.assignments.store', $property), ['user_id' => $admin->id])
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('property_assignments', 0);
    }

    public function test_manager_cannot_manage_assignments_and_staff_is_read_only(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $candidate = User::factory()->create();
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($manager, $workspace, WorkspaceMemberRole::Manager);
        $this->member($staff, $workspace, WorkspaceMemberRole::Staff);
        $this->member($candidate, $workspace, WorkspaceMemberRole::Staff);
        $property = $this->property($workspace, $owner);
        $this->assign($workspace, $property, $manager);
        $this->assign($workspace, $property, $staff);
        $session = ['current_workspace_id' => $workspace->id];

        // Assigned manager/staff can view the assignment list.
        $this->actingAs($manager)->withSession($session)->get(route('app.properties.assignments.index', $property))->assertOk();
        $this->actingAs($staff)->withSession($session)->get(route('app.properties.assignments.index', $property))->assertOk();

        // But neither can create or delete assignments.
        $this->actingAs($manager)->withSession($session)->post(route('app.properties.assignments.store', $property), ['user_id' => $candidate->id])->assertForbidden();
        $this->actingAs($staff)->withSession($session)->post(route('app.properties.assignments.store', $property), ['user_id' => $candidate->id])->assertForbidden();

        $assignment = PropertyAssignment::where('property_id', $property->id)->where('user_id', $staff->id)->firstOrFail();
        $this->actingAs($manager)->withSession($session)->delete(route('app.properties.assignments.destroy', [$property, $assignment]))->assertForbidden();
        $this->actingAs($staff)->withSession($session)->delete(route('app.properties.assignments.destroy', [$property, $assignment]))->assertForbidden();
        $this->assertDatabaseHas('property_assignments', ['id' => $assignment->id]);
    }

    public function test_assigned_vs_unassigned_property_access_for_manager_and_staff(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($manager, $workspace, WorkspaceMemberRole::Manager);
        $this->member($staff, $workspace, WorkspaceMemberRole::Staff);
        $assigned = $this->property($workspace, $owner);
        $unassigned = $this->property($workspace, $owner);
        $this->assign($workspace, $assigned, $manager);
        $this->assign($workspace, $assigned, $staff);
        $session = ['current_workspace_id' => $workspace->id];

        // Assigned: both can view the property and its units.
        $this->actingAs($manager)->withSession($session)->get(route('app.properties.show', $assigned))->assertOk();
        $this->actingAs($staff)->withSession($session)->get(route('app.properties.show', $assigned))->assertOk();
        $this->actingAs($staff)->withSession($session)->get(route('app.properties.units.index', $assigned))->assertOk();

        // Unassigned: both are denied, including unit listing.
        $this->actingAs($manager)->withSession($session)->get(route('app.properties.show', $unassigned))->assertForbidden();
        $this->actingAs($staff)->withSession($session)->get(route('app.properties.show', $unassigned))->assertForbidden();
        $this->actingAs($manager)->withSession($session)->get(route('app.properties.units.index', $unassigned))->assertForbidden();
        $this->actingAs($manager)->withSession($session)->put(route('app.properties.update', $unassigned), [
            'name' => 'Coba Ubah', 'property_type' => 'kost', 'status' => 'active',
        ])->assertForbidden();

        // Owner bypass: full access without any assignment row.
        $this->actingAs($owner)->withSession($session)->get(route('app.properties.show', $unassigned))->assertOk();
        $this->actingAs($owner)->withSession($session)->put(route('app.properties.update', $unassigned), [
            'name' => 'Diubah Pemilik', 'property_type' => 'kost', 'status' => 'active',
        ])->assertRedirect();
    }

    public function test_manager_store_creates_self_assignment_while_owner_store_creates_none(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($manager, $workspace, WorkspaceMemberRole::Manager);

        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('app.properties.store'), [
                'name' => 'Kost Manajer', 'property_type' => 'kost', 'status' => 'active',
            ])
            ->assertRedirect();
        $managerProperty = Property::where('workspace_id', $workspace->id)->where('name', 'Kost Manajer')->sole();
        $this->assertDatabaseHas('property_assignments', [
            'property_id' => $managerProperty->id, 'user_id' => $manager->id, 'created_by' => $manager->id,
        ]);

        // Assigned manager can immediately view the property they created.
        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('app.properties.show', $managerProperty))
            ->assertOk();

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('app.properties.store'), [
                'name' => 'Kost Pemilik', 'property_type' => 'kost', 'status' => 'active',
            ])
            ->assertRedirect();
        $ownerProperty = Property::where('workspace_id', $workspace->id)->where('name', 'Kost Pemilik')->sole();
        $this->assertDatabaseMissing('property_assignments', ['property_id' => $ownerProperty->id]);
    }

    public function test_backfill_is_idempotent_and_scoped_correctly(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $suspended = User::factory()->create();
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($manager, $workspace, WorkspaceMemberRole::Manager);
        $this->member($staff, $workspace, WorkspaceMemberRole::Staff);
        $this->member($suspended, $workspace, WorkspaceMemberRole::Staff, UserStatus::Suspended);
        $this->member($admin, $workspace, WorkspaceMemberRole::Manager);

        $active = $this->property($workspace, $owner);
        $trashed = $this->property($workspace, $owner);
        $trashed->delete();

        (new BackfillPropertyAssignments)->run();
        (new BackfillPropertyAssignments)->run();

        $this->assertSame(2, PropertyAssignment::where('property_id', $active->id)->count());
        $this->assertDatabaseHas('property_assignments', ['property_id' => $active->id, 'user_id' => $manager->id]);
        $this->assertDatabaseHas('property_assignments', ['property_id' => $active->id, 'user_id' => $staff->id]);
        $this->assertDatabaseMissing('property_assignments', ['property_id' => $active->id, 'user_id' => $owner->id]);
        $this->assertDatabaseMissing('property_assignments', ['property_id' => $active->id, 'user_id' => $suspended->id]);
        $this->assertDatabaseMissing('property_assignments', ['property_id' => $active->id, 'user_id' => $admin->id]);
        $this->assertSame(0, PropertyAssignment::where('property_id', $trashed->id)->count());
    }

    public function test_cross_workspace_assignment_access_returns_404(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $foreign = Property::factory()->create();
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)->get(route('app.properties.assignments.index', $foreign))->assertNotFound();
        $this->actingAs($owner)->withSession($session)->post(route('app.properties.assignments.store', $foreign), ['user_id' => $owner->id])->assertNotFound();
    }

    public function test_super_admin_is_denied_assignment_routes(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $property = Property::factory()->create();

        $this->actingAs($admin)->get(route('app.properties.assignments.index', $property))->assertForbidden();
        $this->actingAs($admin)->post(route('app.properties.assignments.store', $property), ['user_id' => 1])->assertForbidden();
    }

    public function test_unassigned_staff_cannot_create_units_but_owner_can(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($staff, $workspace, WorkspaceMemberRole::Staff);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($staff)->withSession($session)->post(route('app.properties.units.store', $property), [
            'unit_number' => 'E-01', 'rental_price' => 1000000, 'rental_period' => 'monthly',
        ])->assertForbidden();

        $this->assign($workspace, $property, $staff);
        $this->actingAs($staff)->withSession($session)->post(route('app.properties.units.store', $property), [
            'unit_number' => 'E-01', 'rental_price' => 1000000, 'rental_period' => 'monthly',
        ])->assertForbidden();
        $this->assertDatabaseMissing('units', ['property_id' => $property->id, 'unit_number' => 'E-01']);

        $this->actingAs($owner)->withSession($session)->post(route('app.properties.units.store', $property), [
            'unit_number' => 'E-01', 'rental_price' => 1000000, 'rental_period' => 'monthly',
        ])->assertRedirect();
        $this->assertDatabaseHas('units', ['property_id' => $property->id, 'unit_number' => 'E-01']);
        $this->assertSame(1, Unit::where('property_id', $property->id)->count());
    }

    public function test_assignment_confirm_markup_is_safely_encoded(): void
    {
        $owner = User::factory()->create();
        $tricky = User::factory()->create(['name' => "O'Brien \"Kutip\" <uji>"]);
        $workspace = $this->workspaceWithRole($owner);
        $this->member($tricky, $workspace, WorkspaceMemberRole::Staff);
        $property = $this->property($workspace, $owner);
        $this->assign($workspace, $property, $tricky);
        $session = ['current_workspace_id' => $workspace->id];

        $response = $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.assignments.index', $property));
        $response->assertOk();

        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('confirm(', $html);
        $this->assertStringNotContainsString("confirm('Hapus penugasan O'Brien", $html);
    }
}
