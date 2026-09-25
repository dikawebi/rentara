<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Property;
use App\PropertyType;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $organization = Organization::query()->where('name', 'Kos Mentari Jakarta')->first();

        if ($organization === null || $organization->properties()->exists()) {
            return;
        }

        Property::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Kos Mentari Tebet',
            'property_type' => PropertyType::Kost,
            'description' => 'Workspace contoh untuk pengembangan lokal.',
            'full_address' => 'Jl. Tebet Barat Dalam, Jakarta Selatan',
            'district' => 'Tebet',
            'city' => 'Jakarta Selatan',
        ]);
    }
}
