<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyAssignmentFactory extends Factory
{
    protected $model = PropertyAssignment::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'property_id' => Property::factory(),
            'user_id' => User::factory(),
            'created_by' => null,
        ];
    }
}
