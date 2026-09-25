<?php

namespace Database\Factories;

use App\ComplaintCategory;
use App\ComplaintPriority;
use App\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Complaint> */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        $tenancy = Tenancy::factory();

        return ['tenancy_id' => $tenancy, 'tenant_id' => fn (array $attributes): int => Tenancy::query()->findOrFail($attributes['tenancy_id'])->tenant_id, 'organization_id' => fn (array $attributes): int => Tenancy::query()->findOrFail($attributes['tenancy_id'])->organization_id, 'unit_id' => fn (array $attributes): int => Tenancy::query()->findOrFail($attributes['tenancy_id'])->unit_id, 'category' => ComplaintCategory::Maintenance, 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'priority' => ComplaintPriority::Normal, 'status' => ComplaintStatus::Open];
    }
}
