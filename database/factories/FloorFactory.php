<?php

namespace Database\Factories;

use App\Models\Floor;
use App\Models\Property;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class FloorFactory extends Factory
{
    protected $model = Floor::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'property_id' => Property::factory(),
            'name' => 'Lantai '.fake()->unique()->bothify('##'),
            'sort_order' => 0,
            'notes' => null,
        ];
    }
}
