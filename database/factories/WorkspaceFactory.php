<?php

namespace Database\Factories;

use App\Enums\WorkspaceStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return ['owner_id' => User::factory(), 'name' => fake()->company(), 'slug' => fake()->unique()->slug(2).'-'.fake()->unique()->numerify('###'), 'status' => WorkspaceStatus::Active, 'timezone' => 'Asia/Jakarta', 'currency' => 'IDR'];
    }

    public function suspended(): static
    {
        return $this->state(['status' => WorkspaceStatus::Suspended]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => WorkspaceStatus::Inactive]);
    }
}
