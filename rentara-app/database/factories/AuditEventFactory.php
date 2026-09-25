<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'organization_id' => null,
            'action' => 'test.action',
            'subject_type' => 'test.subject',
            'subject_id' => fake()->randomNumber(5),
            'metadata' => [],
            'created_at' => now(),
        ];
    }
}
