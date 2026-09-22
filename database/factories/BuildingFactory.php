<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Property;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'property_id' => Property::factory(),
            'name' => 'Gedung '.fake()->unique()->bothify('??-###'),
            'sort_order' => 0,
            'notes' => null,
        ];
    }
}
