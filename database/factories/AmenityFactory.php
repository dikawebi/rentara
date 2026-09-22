<?php

namespace Database\Factories;

use App\Models\Amenity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => fake()->unique()->words(2, true), 'description' => fake()->optional()->sentence(), 'created_by' => User::factory()];
    }
}
