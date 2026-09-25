<?php

namespace Database\Factories;

use App\InvoiceStatus;
use App\InvoiceType;
use App\Models\Invoice;
use App\Models\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenancy_id' => Tenancy::factory(),
            'organization_id' => fn (array $attributes): int => Tenancy::query()->findOrFail($attributes['tenancy_id'])->organization_id,
            'tenant_id' => fn (array $attributes): int => Tenancy::query()->findOrFail($attributes['tenancy_id'])->tenant_id,
            'type' => InvoiceType::Rent,
            'idempotency_key' => 'invoice:'.$this->faker->unique()->uuid(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => now()->startOfMonth()->toDateString(),
            'amount' => fn (array $attributes): int => Tenancy::query()->findOrFail($attributes['tenancy_id'])->monthly_rent,
            'status' => InvoiceStatus::Unpaid,
        ];
    }
}
