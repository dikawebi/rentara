<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Organization;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use App\TenancyStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tenancy> */
class TenancyFactory extends Factory
{
    protected $model = Tenancy::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = now()->addDays(14)->startOfDay();

        return [
            'tenant_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'unit_id' => Unit::factory(),
            'booking_id' => Booking::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->subDay()->toDateString(),
            'monthly_rent' => 1800000,
            'deposit_amount' => null,
            'terms_snapshot' => ['monthly_rent' => 1800000],
            'status' => TenancyStatus::Active,
        ];
    }
}
