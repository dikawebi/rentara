<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\OrganizationRole;
use App\PropertyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OrganizationPropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_property_and_manage_its_units(): void
    {
        [$owner, $organization] = $this->createOrganizationMember(OrganizationRole::Owner);

        $this->actingAs($owner)
            ->post(route('organizations.properties.store', $organization), [
                'name' => 'Kos Mentari Tebet',
                'property_type' => PropertyType::Kost->value,
                'description' => 'Hunian dekat transportasi umum.',
                'full_address' => 'Jl. Tebet Barat Dalam, Jakarta Selatan',
                'district' => 'Tebet',
                'city' => 'Jakarta Selatan',
            ])
            ->assertRedirect();

        $property = Property::query()->where('name', 'Kos Mentari Tebet')->firstOrFail();

        $this->assertSame($organization->id, $property->organization_id);

        $this->get(route('organizations.properties.show', [$organization, $property]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Properties/Show')
                ->where('property.full_address', 'Jl. Tebet Barat Dalam, Jakarta Selatan')
                ->has('units', 0));

        $this->post(route('organizations.properties.units.store', [$organization, $property]), [
            'name' => 'Kamar A1',
            'capacity' => 2,
            'monthly_price' => 1750000,
        ])->assertSessionHas('status', 'unit-created');

        $this->assertDatabaseHas('units', [
            'property_id' => $property->id,
            'name' => 'Kamar A1',
            'monthly_price' => 1750000,
        ]);

        $unit = Unit::query()->where('property_id', $property->id)->firstOrFail();
        $this->patch(route('organizations.properties.units.update', [$organization, $property, $unit]), [
            'name' => 'Kamar A1 Premium',
            'capacity' => 2,
            'monthly_price' => 2100000,
            'status' => 'unavailable',
        ])->assertSessionHas('status', 'unit-updated');

        $this->patch(route('organizations.properties.update', [$organization, $property]), [
            'name' => 'Kos Mentari Tebet Baru',
            'property_type' => PropertyType::Kost->value,
            'description' => 'Deskripsi yang diperbarui.',
            'full_address' => 'Jl. Tebet Barat Dalam, Jakarta Selatan',
            'district' => 'Tebet',
            'city' => 'Jakarta Selatan',
        ])->assertSessionHas('status', 'property-updated');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'Kamar A1 Premium',
            'status' => 'unavailable',
        ]);
        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'name' => 'Kos Mentari Tebet Baru',
        ]);
    }

    public function test_manager_can_manage_properties_but_staff_has_read_only_access(): void
    {
        [$manager, $organization] = $this->createOrganizationMember(OrganizationRole::Manager);
        $property = Property::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($manager)
            ->post(route('organizations.properties.units.store', [$organization, $property]), [
                'name' => 'Unit Manager',
                'capacity' => 1,
                'monthly_price' => 1250000,
            ])
            ->assertSessionHas('status', 'unit-created');

        $staff = User::factory()->create();
        $organization->memberships()->create([
            'user_id' => $staff->id,
            'role' => OrganizationRole::Staff,
            'accepted_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('organizations.properties.index', $organization))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Properties/Index')
                ->where('canManage', false)
                ->has('properties', 1));

        $this->post(route('organizations.properties.units.store', [$organization, $property]), [
            'name' => 'Unit Tidak Boleh',
            'capacity' => 1,
            'monthly_price' => 1000000,
        ])->assertForbidden();

        $this->assertDatabaseMissing('units', ['name' => 'Unit Tidak Boleh']);
    }

    public function test_property_urls_are_scoped_to_the_organization(): void
    {
        [$owner, $otherOrganization] = $this->createOrganizationMember(OrganizationRole::Owner);
        $property = Property::factory()->create();

        $this->actingAs($owner)
            ->get(route('organizations.properties.show', [$otherOrganization, $property]))
            ->assertNotFound();
    }

    public function test_owner_can_archive_a_property_and_archived_properties_are_hidden(): void
    {
        [$owner, $organization] = $this->createOrganizationMember(OrganizationRole::Owner);
        $property = Property::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($owner)
            ->delete(route('organizations.properties.destroy', [$organization, $property]))
            ->assertRedirect(route('organizations.properties.index', $organization));

        $this->assertSoftDeleted($property);
        $this->get(route('organizations.properties.index', $organization))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('properties', 0));
        $this->get(route('organizations.properties.show', [$organization, $property]))->assertNotFound();
    }

    public function test_property_and_unit_input_is_validated(): void
    {
        [$owner, $organization] = $this->createOrganizationMember(OrganizationRole::Owner);

        $this->actingAs($owner)
            ->post(route('organizations.properties.store', $organization), [
                'name' => '',
                'property_type' => 'apartment',
                'full_address' => '',
                'city' => 'Jakarta',
            ])
            ->assertSessionHasErrors(['name', 'property_type', 'full_address']);

        $property = Property::factory()->create(['organization_id' => $organization->id]);

        $this->post(route('organizations.properties.units.store', [$organization, $property]), [
            'name' => 'Unit Harga Negatif',
            'capacity' => 0,
            'monthly_price' => -1,
        ])->assertSessionHasErrors(['capacity', 'monthly_price']);

        $this->assertDatabaseCount('units', 0);
    }

    public function test_owner_can_configure_identity_document_requirements_per_property(): void
    {
        [$owner, $organization] = $this->createOrganizationMember(OrganizationRole::Owner);

        $this->actingAs($owner)
            ->post(route('organizations.properties.store', $organization), [
                'name' => 'Kontrakan Menteng',
                'property_type' => PropertyType::RentalHouse->value,
                'full_address' => 'Jl. Menteng Raya, Jakarta Pusat',
                'city' => 'Jakarta Pusat',
                'identity_document_requirements' => ['passport', 'student_card'],
            ])
            ->assertRedirect();

        $property = Property::query()->where('name', 'Kontrakan Menteng')->firstOrFail();
        $this->assertSame(['passport', 'student_card'], $property->requiredIdentityDocumentTypes());

        $this->patch(route('organizations.properties.update', [$organization, $property]), [
            'name' => 'Kontrakan Menteng',
            'property_type' => PropertyType::RentalHouse->value,
            'description' => null,
            'full_address' => 'Jl. Menteng Raya, Jakarta Pusat',
            'district' => null,
            'city' => 'Jakarta Pusat',
            'identity_document_requirements' => [],
        ])->assertSessionHas('status', 'property-updated');

        $this->assertSame([], $property->fresh()->requiredIdentityDocumentTypes());
    }

    /** @return array{User, Organization} */
    private function createOrganizationMember(OrganizationRole $role): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
            'accepted_at' => now(),
        ]);

        return [$user, $organization];
    }
}
