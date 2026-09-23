<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'event' => AuditLogger::LOGIN_DENIED,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

}
