<?php

namespace Database\Factories;

use App\ApplicationStatus;
use App\Models\Listing;
use App\Models\RentalApplication;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalApplication>
 */
class RentalApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'applicant_id' => User::factory(),
            'applicant_snapshot' => ['name' => fake()->name(), 'email' => fake()->safeEmail()],
            'unit_id' => Unit::factory(),
            'requested_move_in' => now()->addDays(14)->toDateString(),
            'requested_duration_months' => 12,
            'applicant_note' => fake()->sentence(),
            'privacy_notice_version' => config('rentara.privacy_notice_version'),
            'privacy_accepted_at' => now(),
            'status' => ApplicationStatus::Submitted,
            'listing_snapshot' => [],
        ];
    }
}
