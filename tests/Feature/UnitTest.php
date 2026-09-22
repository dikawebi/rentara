<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceMemberRole;
use App\Models\Building;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class UnitTest extends TestCase
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

    private function unitPayload(array $overrides = []): array
    {
        return array_merge([
            'unit_number' => 'A-101',
            'name' => 'Kamar Utama',
            'area' => '24.50',
            'rental_price' => 1500000,
            'rental_period' => 'monthly',
            'capacity' => 2,
            'status' => 'available',
        ], $overrides);
    }

    public function test_owner_can_crud_unit_happy_path(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $building = Building::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
        $unitType = UnitType::factory()->create(['workspace_id' => $workspace->id]);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.units.index', $property))
            ->assertOk()
            ->assertSee('Belum ada unit');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload([
                'building_id' => $building->id,
                'unit_type_id' => $unitType->id,
            ]))
            ->assertRedirect();

        $unit = Unit::where('property_id', $property->id)->sole();
        $this->assertSame('A-101', $unit->unit_number);
        $this->assertSame($building->id, $unit->building_id);
        $this->assertSame($unitType->id, $unit->unit_type_id);
        $this->assertSame(1500000, $unit->rental_price);

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.properties.units.index', $property))
            ->assertOk()
            ->assertSee('A-101')
            ->assertSee('Tersedia');

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.properties.units.update', [$property, $unit]), $this->unitPayload(['unit_number' => 'A-102', 'status' => 'occupied']))
            ->assertRedirect();
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'unit_number' => 'A-102', 'status' => 'occupied']);

        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.properties.units.destroy', [$property, $unit]))
            ->assertRedirect();
        $this->assertSoftDeleted('units', ['id' => $unit->id]);

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.restore', [$property, $unit]))
            ->assertRedirect();
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'deleted_at' => null]);
    }

    public function test_unit_validation_rejects_bad_input(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload(['unit_number' => null]))
            ->assertSessionHasErrors('unit_number');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload(['rental_price' => -100]))
            ->assertSessionHasErrors('rental_price');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload(['rental_period' => 'weekly']))
            ->assertSessionHasErrors('rental_period');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload(['status' => 'sold']))
            ->assertSessionHasErrors('status');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload(['area' => 'bukan-angka', 'capacity' => 0]))
            ->assertSessionHasErrors(['area', 'capacity']);

        $this->assertDatabaseCount('units', 0);
    }

    public function test_duplicate_unit_number_rejected_in_same_property_but_allowed_across_properties(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $first = $this->property($workspace, $owner);
        $second = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $first), $this->unitPayload(['unit_number' => 'B-01']))
            ->assertRedirect();

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $first), $this->unitPayload(['unit_number' => 'B-01']))
            ->assertSessionHasErrors('unit_number');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $second), $this->unitPayload(['unit_number' => 'B-01']))
            ->assertRedirect();
        $this->assertDatabaseHas('units', ['property_id' => $second->id, 'unit_number' => 'B-01']);
    }

    public function test_sibling_foreign_keys_must_belong_to_same_property_or_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $other = $this->property($workspace, $owner);
        $foreignBuilding = Building::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $other->id]);
        $foreignUnitType = UnitType::factory()->create();
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload(['building_id' => $foreignBuilding->id]))
            ->assertSessionHasErrors('building_id');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload(['unit_type_id' => $foreignUnitType->id]))
            ->assertSessionHasErrors('unit_type_id');

        $this->assertDatabaseCount('units', 0);
    }

    public function test_cross_property_and_cross_workspace_unit_access_returns_404(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $other = $this->property($workspace, $owner);
        $unit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $other->id, 'unit_number' => 'Z-99']);
        $foreign = Unit::factory()->create(['unit_number' => 'Z-98']);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)->get(route('app.properties.units.edit', [$property, $unit]))->assertNotFound();
        $this->actingAs($owner)->withSession($session)->put(route('app.properties.units.update', [$property, $unit]), $this->unitPayload())->assertNotFound();
        $this->actingAs($owner)->withSession($session)->delete(route('app.properties.units.destroy', [$property, $unit]))->assertNotFound();
        $this->actingAs($owner)->withSession($session)->get(route('app.properties.units.index', $foreign->property_id))->assertNotFound();
        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_unit_restore_conflict_returns_422(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        // The hard unique index normally makes this state unreachable via HTTP;
        // drop it in-test to simulate the race the 422 pre-check guards against.
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'unit_number']);
        });

        $unit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'unit_number' => 'C-01']);
        $unit->delete();
        Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'unit_number' => 'C-01']);

        $response = $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.restore', [$property, $unit]));
        $response->assertStatus(422);
        $this->assertStringContainsString('sudah digunakan', $response->exception?->getMessage() ?? '');
        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }

    public function test_unit_role_matrix_assigned_manager_owner_bypass_staff_read_only(): void
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

        $this->actingAs($manager)->withSession($session)->get(route('app.properties.units.index', $property))->assertForbidden();
        $this->actingAs($manager)->withSession($session)->post(route('app.properties.units.store', $property), $this->unitPayload())->assertForbidden();

        $this->assign($workspace, $property, $manager);
        $this->assign($workspace, $property, $staff);

        $this->actingAs($manager)->withSession($session)->post(route('app.properties.units.store', $property), $this->unitPayload(['unit_number' => 'D-01']))->assertRedirect();

        $this->actingAs($staff)->withSession($session)->get(route('app.properties.units.index', $property))->assertOk()->assertSee('D-01');
        $this->actingAs($staff)->withSession($session)->post(route('app.properties.units.store', $property), $this->unitPayload(['unit_number' => 'D-02']))->assertForbidden();

        $unit = Unit::where('property_id', $property->id)->where('unit_number', 'D-01')->firstOrFail();
        $this->actingAs($staff)->withSession($session)->put(route('app.properties.units.update', [$property, $unit]), $this->unitPayload(['unit_number' => 'D-01']))->assertForbidden();
        $this->actingAs($staff)->withSession($session)->delete(route('app.properties.units.destroy', [$property, $unit]))->assertForbidden();
    }

    public function test_unit_type_crud_duplicates_and_restore_conflict(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->get(route('app.unit-types.index'))
            ->assertOk()
            ->assertSee('Belum ada tipe unit');

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.unit-types.store'), ['name' => 'Standar', 'description' => 'Kamar standar', 'default_capacity' => 2])
            ->assertRedirect();
        $unitType = UnitType::where('workspace_id', $workspace->id)->sole();

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.unit-types.store'), ['name' => 'Standar'])
            ->assertSessionHasErrors('name');

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.unit-types.update', $unitType), ['name' => 'Deluxe', 'default_capacity' => 3])
            ->assertRedirect();
        $this->assertDatabaseHas('unit_types', ['id' => $unitType->id, 'name' => 'Deluxe']);

        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.unit-types.destroy', $unitType))
            ->assertRedirect();
        $this->assertSoftDeleted('unit_types', ['id' => $unitType->id]);

        // The hard unique index normally makes this state unreachable via HTTP;
        // drop it in-test to simulate the race the 422 pre-check guards against.
        Schema::table('unit_types', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'name']);
        });

        UnitType::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Deluxe']);
        $response = $this->actingAs($owner)->withSession($session)
            ->post(route('app.unit-types.restore', $unitType));
        $response->assertStatus(422);
        $this->assertSoftDeleted('unit_types', ['id' => $unitType->id]);
    }

    public function test_unit_type_name_may_repeat_across_workspaces(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $other = Workspace::factory()->create();
        $this->member($owner, $other, WorkspaceMemberRole::Owner);
        UnitType::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Standar']);

        $this->actingAs($owner)->withSession(['current_workspace_id' => $other->id])
            ->post(route('app.unit-types.store'), ['name' => 'Standar'])
            ->assertRedirect(route('app.unit-types.index'));
        $this->assertDatabaseHas('unit_types', ['workspace_id' => $other->id, 'name' => 'Standar']);
    }

    public function test_unit_type_staff_read_only_and_super_admin_denied(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $this->member($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->member($staff, $workspace, WorkspaceMemberRole::Staff);
        $unitType = UnitType::factory()->create(['workspace_id' => $workspace->id]);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($staff)->withSession($session)->get(route('app.unit-types.index'))->assertOk();
        $this->actingAs($staff)->withSession($session)->post(route('app.unit-types.store'), ['name' => 'Baru'])->assertForbidden();
        $this->actingAs($staff)->withSession($session)->put(route('app.unit-types.update', $unitType), ['name' => 'Ubah'])->assertForbidden();
        $this->actingAs($staff)->withSession($session)->delete(route('app.unit-types.destroy', $unitType))->assertForbidden();

        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->actingAs($admin)->get(route('app.unit-types.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('app.unit-types.store'), ['name' => 'Baru'])->assertForbidden();
    }

    public function test_super_admin_is_denied_unit_routes(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $property = Property::factory()->create();

        $this->actingAs($admin)->get(route('app.properties.units.index', $property))->assertForbidden();
        $this->actingAs($admin)->post(route('app.properties.units.store', $property), $this->unitPayload())->assertForbidden();
    }

    public function test_trashed_unit_number_reuse_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload(['unit_number' => 'A-Arsip']))
            ->assertRedirect();

        $unit = Unit::where('property_id', $property->id)->sole();
        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.properties.units.destroy', [$property, $unit]))
            ->assertRedirect();

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload(['unit_number' => 'A-Arsip']))
            ->assertSessionHasErrors('unit_number');
        $this->assertSame(1, Unit::withTrashed()->where('property_id', $property->id)->count());
    }

    public function test_trashed_unit_type_name_reuse_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.unit-types.store'), ['name' => 'Tipe Arsip'])
            ->assertRedirect();

        $unitType = UnitType::where('workspace_id', $workspace->id)->sole();
        $this->actingAs($owner)->withSession($session)
            ->delete(route('app.unit-types.destroy', $unitType))
            ->assertRedirect();

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.unit-types.store'), ['name' => 'Tipe Arsip'])
            ->assertSessionHasErrors('name');
        $this->assertSame(1, UnitType::withTrashed()->where('workspace_id', $workspace->id)->count());
    }

    public function test_update_without_optional_fields_preserves_capacity_and_status(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $session = ['current_workspace_id' => $workspace->id];

        $this->actingAs($owner)->withSession($session)
            ->post(route('app.properties.units.store', $property), $this->unitPayload([
                'unit_number' => 'P-01', 'capacity' => 4, 'status' => 'occupied',
            ]))
            ->assertRedirect();
        $unit = Unit::where('property_id', $property->id)->sole();
        $this->assertSame(4, $unit->capacity);

        $payload = $this->unitPayload(['unit_number' => 'P-01']);
        unset($payload['capacity'], $payload['status']);

        $this->actingAs($owner)->withSession($session)
            ->put(route('app.properties.units.update', [$property, $unit]), $payload)
            ->assertRedirect();
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'capacity' => 4, 'status' => 'occupied']);
    }

    public function test_capacity_cannot_be_reduced_below_active_tenant_count(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->workspaceWithRole($owner);
        $property = $this->property($workspace, $owner);
        $unit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'capacity' => 2]);
        Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id, 'status' => 'active']);
        Tenant::factory()->create(['workspace_id' => $workspace->id, 'unit_id' => $unit->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->withSession(['current_workspace_id' => $workspace->id])
            ->put(route('app.properties.units.update', [$property, $unit]), $this->unitPayload(['capacity' => 1]));

        $response->assertSessionHasErrors('capacity');
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'capacity' => 2]);
    }
}
