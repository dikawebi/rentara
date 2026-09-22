<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(), 'unit_id' => null,
            'name' => fake()->name(), 'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(), 'identity_number' => fake()->numerify('################'),
            'date_of_birth' => fake()->date(), 'gender' => 'Laki-laki',
            'occupation' => fake()->jobTitle(), 'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(), 'status' => TenantStatus::Active,
        ];
    }
}
