<?php

namespace Database\Factories;

use App\BookingStatus;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\RentalApplication;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = now()->addDays(14)->startOfDay();

        return [
            'rental_application_id' => RentalApplication::factory(),
            'organization_id' => Organization::factory(),
            'unit_id' => Unit::factory(),
            'requested_move_in' => $start->toDateString(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->subDay()->toDateString(),
            'terms_snapshot' => ['rent' => 0],
            'status' => BookingStatus::Verified,
            'expires_at' => null,
            'booking_expiry_days' => null,
        ];
    }
}
