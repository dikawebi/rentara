<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceMemberRole;
use App\Enums\UserStatus;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(): array
    {
        $owner = User::factory()->create(); $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceMemberRole::Owner]);
        $property = Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $unit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'capacity' => 2]);
        return [$owner, $workspace, $property, $unit];
    }

    private function payload(Unit $unit, array $extra = []): array
    {
        return array_merge(['name' => 'Budi', 'phone' => '0812', 'email' => 'budi@example.test', 'unit_id' => $unit->id, 'status' => 'active', 'identity_number' => 'SANGAT-RAHASIA'], $extra);
    }

    public function test_owner_can_crud_assign_and_soft_delete_restore_tenant(): void
    {
        [$owner, $workspace, , $unit] = $this->setupWorkspace(); $session = ['current_workspace_id' => $workspace->id];
        $response = $this->actingAs($owner)->withSession($session)->post(route('app.tenants.store'), $this->payload($unit));
        $tenant = Tenant::sole(); $response->assertRedirect(route('app.tenants.show', $tenant));
        $this->assertSame('SANGAT-RAHASIA', $tenant->fresh()->identity_number);
        $this->actingAs($owner)->withSession($session)->put(route('app.tenants.update', $tenant), $this->payload($unit, ['name' => 'Citra', 'status' => 'inactive']))->assertRedirect();
        $this->actingAs($owner)->withSession($session)->delete(route('app.tenants.destroy', $tenant))->assertRedirect();
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->actingAs($owner)->withSession($session)->post(route('app.tenants.restore', $tenant))->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'deleted_at' => null, 'unit_id' => $unit->id]);
    }

    public function test_active_capacity_allows_multiple_tenants_but_excludes_inactive(): void
    {
        [$owner, $workspace, , $unit] = $this->setupWorkspace(); $session = ['current_workspace_id' => $workspace->id];
        foreach (['A', 'B'] as $name) $this->actingAs($owner)->withSession($session)->post(route('app.tenants.store'), $this->payload($unit, ['name' => $name]))->assertRedirect();
        $third = $this->actingAs($owner)->withSession($session)->post(route('app.tenants.store'), $this->payload($unit, ['name' => 'C'])); $third->assertSessionHasErrors('unit_id');
        $first = Tenant::where('name', 'A')->firstOrFail();
        $this->actingAs($owner)->withSession($session)->put(route('app.tenants.update', $first), $this->payload($unit, ['name' => 'A', 'status' => 'inactive']))->assertRedirect();
        $this->actingAs($owner)->withSession($session)->post(route('app.tenants.store'), $this->payload($unit, ['name' => 'C']))->assertRedirect();
    }

    public function test_assignment_is_workspace_scoped_and_active_unit_delete_is_blocked(): void
    {
        [$owner, $workspace, $property, $unit] = $this->setupWorkspace(); $session = ['current_workspace_id' => $workspace->id];
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);
        $this->actingAs($owner)->withSession($session)->get(route('app.tenants.show', Tenant::factory()->create()))->assertNotFound();
        $this->actingAs($owner)->withSession($session)->delete(route('app.properties.units.destroy', [$property, $unit]))->assertSessionHasErrors('unit');
    }

    public function test_unit_with_only_inactive_tenants_can_be_deleted_and_tenant_reference_is_cleared(): void
    {
        [$owner, $workspace, $property, $unit] = $this->setupWorkspace();
        $tenant = Tenant::factory()->create([
            'workspace_id' => $workspace->id,
            'unit_id' => $unit->id,
            'status' => 'inactive',
        ]);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->delete(route('app.properties.units.destroy', [$property, $unit]))
            ->assertRedirect();

        $this->assertSoftDeleted('units', ['id' => $unit->id]);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'unit_id' => $unit->id]);

        $unit->forceDelete();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'unit_id' => null]);
    }

    public function test_workspace_delete_cascades_tenants_without_orphans(): void
    {
        [$owner, $workspace, , $unit] = $this->setupWorkspace();
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);

        $workspace->forceDelete();

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_assigned_manager_manages_and_staff_reads_only(): void
    {
        [$owner, $workspace, $property, $unit] = $this->setupWorkspace(); $manager = User::factory()->create(); $staff = User::factory()->create();
        foreach ([[$manager, WorkspaceMemberRole::Manager], [$staff, WorkspaceMemberRole::Staff]] as [$user, $role]) WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role]);
        foreach ([$manager, $staff] as $user) PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'user_id' => $user->id]);
        $session = ['current_workspace_id' => $workspace->id]; $payload = $this->payload($unit);
        $this->actingAs($manager)->withSession($session)->post(route('app.tenants.store'), $payload)->assertRedirect();
        $tenant = Tenant::latest('id')->first();
        $this->actingAs($staff)->withSession($session)->get(route('app.tenants.show', $tenant))->assertOk();
        $this->actingAs($staff)->withSession($session)->put(route('app.tenants.update', $tenant), $payload)->assertForbidden();
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('app.tenants.index'))->assertForbidden();
    }

    public function test_manager_cannot_update_tenant_from_another_property(): void
    {
        [$owner, $workspace, $propertyA, $unitA] = $this->setupWorkspace();
        $propertyB = Property::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $unitB = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $propertyB->id]);
        $manager = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $manager->id, 'role' => WorkspaceMemberRole::Manager]);
        PropertyAssignment::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $propertyB->id, 'user_id' => $manager->id]);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unitA->id]);

        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])
            ->put(route('app.tenants.update', $tenant), $this->payload($unitB, ['name' => 'IDOR']))
            ->assertForbidden();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => $tenant->name, 'unit_id' => $unitA->id]);
    }

    public function test_manager_authorized_for_current_unit_can_unassign_tenant(): void
    {
        [$owner, $workspace, $property, $unit] = $this->setupWorkspace();
        $manager = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $manager->id,
            'role' => WorkspaceMemberRole::Manager,
        ]);
        PropertyAssignment::factory()->create([
            'workspace_id' => $workspace->id, 'property_id' => $property->id,
            'user_id' => $manager->id,
        ]);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id]);

        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])
            ->put(route('app.tenants.update', $tenant), $this->payload($unit, ['unit_id' => null]))
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'unit_id' => null]);
    }

    public function test_manager_cannot_view_or_update_already_unassigned_tenant(): void
    {
        [$owner, $workspace, , $unit] = $this->setupWorkspace();
        $manager = User::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id, 'user_id' => $manager->id,
            'role' => WorkspaceMemberRole::Manager,
        ]);
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => null]);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($manager)->withSession($session)
            ->get(route('app.tenants.show', $tenant))->assertForbidden();
        $this->actingAs($manager)->withSession($session)
            ->put(route('app.tenants.update', $tenant), $this->payload($unit, ['unit_id' => null]))
            ->assertForbidden();
    }

    public function test_restore_rejects_a_deleted_referenced_unit_without_resurrecting_it(): void
    {
        [$owner, $workspace, , $unit] = $this->setupWorkspace();
        $tenant = Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id, 'status' => 'inactive']);
        $tenant->delete();
        $unit->delete();

        $response = $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('app.tenants.restore', $tenant));

        $response->assertSessionHasErrors('unit_id');
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }

    public function test_inactive_member_is_denied_and_unassigned_tenant_is_not_visible_to_staff(): void
    {
        [$owner, $workspace, , $unit] = $this->setupWorkspace();
        $staff = User::factory()->create();
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $staff->id, 'role' => WorkspaceMemberRole::Staff, 'status' => UserStatus::Suspended]);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($staff)->withSession($session)->get(route('app.tenants.index'))->assertStatus(409);

        WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $staff->id)->update(['status' => UserStatus::Active]);
        Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => null, 'name' => 'Tanpa Unit']);
        $this->actingAs($staff)->withSession($session)->get(route('app.tenants.index'))->assertOk()->assertDontSee('Tanpa Unit');
    }

    public function test_sensitive_fields_are_encrypted_and_not_disclosed_in_list(): void
    {
        [$owner, $workspace, , $unit] = $this->setupWorkspace();
        $session = ['current_workspace_id' => $workspace->id];
        $this->actingAs($owner)->withSession($session)->post(route('app.tenants.store'), $this->payload($unit, [
            'identity_number' => 'ID-SECRET', 'date_of_birth' => '1990-01-01', 'gender' => 'Rahasia',
            'occupation' => 'Rahasia', 'emergency_contact_name' => 'Kontak Rahasia', 'emergency_contact_phone' => '08123456',
        ]))->assertRedirect();
        $tenant = Tenant::sole();
        $raw = $tenant->getRawOriginal('identity_number');
        $this->assertNotSame('ID-SECRET', $raw);
        $this->actingAs($owner)->withSession($session)->get(route('app.tenants.index'))
            ->assertOk()->assertDontSee('ID-SECRET')->assertDontSee('Kontak Rahasia');
    }

    public function test_owner_tenant_list_includes_unassigned_tenants(): void
    {
        [$owner, $workspace, , $unit] = $this->setupWorkspace();
        Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id, 'name' => 'Assigned Tenant']);
        Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => null, 'name' => 'Unassigned Tenant']);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('app.tenants.index'))->assertOk()->assertSee('Assigned Tenant')->assertSee('Unassigned Tenant');
    }
}
