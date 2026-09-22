<?php

namespace Database\Factories;

use App\Models\UnitType;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitTypeFactory extends Factory
{
    protected $model = UnitType::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->words(2, true).' Type',
            'description' => fake()->optional()->sentence(),
            'default_capacity' => fake()->optional()->numberBetween(1, 4),
        ];
    }
}
