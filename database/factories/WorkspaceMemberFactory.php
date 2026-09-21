<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkspaceMemberFactory extends Factory
{
    protected $model = WorkspaceMember::class;

    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'user_id' => User::factory(), 'role' => WorkspaceMemberRole::Staff, 'status' => UserStatus::Active, 'joined_at' => now()];
    }

    public function suspended(): static
    {
        return $this->state(['status' => UserStatus::Suspended]);
    }
}
