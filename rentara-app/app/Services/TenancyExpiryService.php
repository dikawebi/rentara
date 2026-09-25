<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\Tenancy;
use App\Models\User;
use App\UnitStatus;

class TenancyExpiryService
{
    public function end(Tenancy $tenancy, ?User $actor = null, string $reason = 'Tenancy ended'): bool
    {
        if ($tenancy->status->value !== 'active') {
            return false;
        }

        $unit = $tenancy->unit()->lockForUpdate()->firstOrFail();
        $tenancy->forceFill([
            'status' => 'ended',
            'ended_at' => now(),
            'ended_reason' => $reason,
        ])->save();

        $hasActiveBooking = Booking::query()
            ->where('unit_id', $unit->id)
            ->whereIn('status', ['verified', 'completed'])
            ->exists();
        $hasActiveTenancy = Tenancy::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveBooking && ! $hasActiveTenancy) {
            $unit->forceFill(['status' => UnitStatus::Available])->save();
        }

        AuditEvent::record(
            $actor,
            $tenancy->organization ?? Organization::find($tenancy->organization_id),
            'tenancy.ended',
            $tenancy,
            ['status' => 'ended', 'reason' => $reason],
        );
        $tenancy->loadMissing('tenant');
        app(NotificationService::class)->send($tenancy->tenant, 'tenancy.ended:'.$tenancy->id, $tenancy->id, 'Tenancy berakhir', 'Tenancy sewamu telah berakhir.', route('dashboard'));

        return true;
    }
}
