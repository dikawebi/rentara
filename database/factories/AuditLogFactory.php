<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

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

    public function configure(): static
    {
        return $this->afterCreating(function (AuditLog $log): void {
            $fingerprint = 'sha256:'.hash('sha256', (string) fake()->userAgent());

            DB::table('audit_logs')->where('id', $log->getKey())->update(['user_agent' => $fingerprint]);

            $log->setRawAttributes(array_merge($log->getAttributes(), ['user_agent' => $fingerprint]), true);
        });
    }
}
