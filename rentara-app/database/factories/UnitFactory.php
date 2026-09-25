<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Unit;
use App\UnitStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => 'Unit '.fake()->unique()->numberBetween(1, 9999),
            'capacity' => fake()->numberBetween(1, 4),
            'monthly_price' => fake()->numberBetween(750000, 8000000),
            'status' => UnitStatus::Available,
        ];
    }
}
