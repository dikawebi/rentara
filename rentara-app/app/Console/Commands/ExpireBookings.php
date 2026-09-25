<?php

namespace App\Console\Commands;

use App\BookingStatus;
use App\Models\Booking;
use App\Services\BookingExpiryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Expire verified bookings whose payment window has elapsed';

    public function handle(BookingExpiryService $expiryService): int
    {
        $expired = 0;
        Booking::query()
            ->where('status', BookingStatus::Verified)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id')
            ->each(function (int $id) use ($expiryService, &$expired): void {
                DB::transaction(function () use ($id, $expiryService, &$expired): void {
                    $booking = Booking::query()->lockForUpdate()->find($id);
                    if ($booking !== null && $expiryService->expireLocked($booking)) {
                        $expired++;
                    }
                }, attempts: 3);
            });

        $this->info("Expired {$expired} booking(s).");

        return self::SUCCESS;
    }
}
