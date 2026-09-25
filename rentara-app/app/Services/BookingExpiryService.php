<?php

namespace App\Services;

use App\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\Tenancy;
use App\Models\User;
use App\UnitStatus;

class BookingExpiryService
{
    public function expire(Booking $booking, ?User $actor = null, string $reason = 'Booking expired'): bool
    {
        if ($booking->status !== BookingStatus::Verified || $booking->expires_at === null || $booking->expires_at->isFuture()) {
            return false;
        }

        $unit = $booking->unit()->lockForUpdate()->firstOrFail();
        $booking->forceFill([
            'status' => BookingStatus::Expired,
            'expired_at' => now(),
            'expiry_reason' => $reason,
            'expired_by' => $actor?->id,
        ])->save();

        $hasActiveBooking = Booking::query()
            ->where('unit_id', $unit->id)
            ->whereIn('status', [BookingStatus::Verified, BookingStatus::Completed])
            ->exists();
        $hasActiveTenancy = Tenancy::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'active')
            ->exists();
        if (! $hasActiveBooking && ! $hasActiveTenancy) {
            $unit->forceFill(['status' => UnitStatus::Available])->save();
        }

        AuditEvent::record($actor, $booking->organization ?? Organization::find($booking->organization_id), 'booking.expired', $booking, [
            'status' => BookingStatus::Expired->value,
            'reason' => $reason,
        ]);
        $booking->loadMissing('rentalApplication.applicant');
        app(NotificationService::class)->send(
            $booking->rentalApplication->applicant,
            'booking.expired:'.$booking->id,
            $booking->id,
            'Booking kedaluwarsa',
            'Booking sewamu telah kedaluwarsa.',
            route('applications.show', $booking->rentalApplication),
        );

        return true;
    }

    public function expireLocked(Booking $booking, ?User $actor = null, string $reason = 'Booking expired'): bool
    {
        return $this->expire($booking, $actor, $reason);
    }
}
