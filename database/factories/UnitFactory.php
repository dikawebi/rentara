<?php

namespace Database\Factories;

use App\Enums\RentalPeriod;
use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Unit;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'property_id' => Property::factory(),
            'building_id' => null,
            'floor_id' => null,
            'block_id' => null,
            'unit_type_id' => null,
            'unit_number' => fake()->unique()->bothify('???-###'),
            'name' => null,
            'area' => null,
            'rental_price' => fake()->numberBetween(500000, 5000000),
            'rental_period' => RentalPeriod::Monthly,
            'capacity' => 1,
            'status' => UnitStatus::Available,
        ];
    }
}
