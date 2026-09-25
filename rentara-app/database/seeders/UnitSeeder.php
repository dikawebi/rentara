<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Unit;
use App\UnitStatus;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $property = Property::query()->where('name', 'Kos Mentari Tebet')->first();

        if ($property === null || $property->units()->exists()) {
            return;
        }

        Unit::factory()->create([
            'property_id' => $property->id,
            'name' => 'Kamar A1',
            'capacity' => 1,
            'monthly_price' => 1800000,
            'status' => UnitStatus::Available,
        ]);

        Unit::factory()->create([
            'property_id' => $property->id,
            'name' => 'Kamar A2',
            'capacity' => 2,
            'monthly_price' => 2400000,
            'status' => UnitStatus::Available,
        ]);
    }
}
