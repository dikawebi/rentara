<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Property;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlockFactory extends Factory
{
    protected $model = Block::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'property_id' => Property::factory(),
            'name' => 'Blok '.fake()->unique()->bothify('?-###'),
            'sort_order' => 0,
            'notes' => null,
        ];
    }
}
