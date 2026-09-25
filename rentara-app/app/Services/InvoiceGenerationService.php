<?php

namespace App\Services;

use App\InvoiceStatus;
use App\InvoiceType;
use App\Models\Invoice;
use App\Models\Tenancy;
use Carbon\CarbonImmutable;

class InvoiceGenerationService
{
    public function createDeposit(Tenancy $tenancy): ?Invoice
    {
        if (($tenancy->deposit_amount ?? 0) <= 0) {
            return null;
        }

        return Invoice::query()->firstOrCreate(
            ['idempotency_key' => 'tenancy:'.$tenancy->id.':deposit'],
            [
                'tenancy_id' => $tenancy->id,
                'organization_id' => $tenancy->organization_id,
                'tenant_id' => $tenancy->tenant_id,
                'type' => InvoiceType::Deposit,
                'due_date' => $tenancy->start_date,
                'amount' => $tenancy->deposit_amount,
                'status' => InvoiceStatus::Unpaid,
            ],
        );
    }

    public function generateRentFor(Tenancy $tenancy, CarbonImmutable $asOf): ?Invoice
    {
        if ($tenancy->status->value !== 'active') {
            return null;
        }

        $start = CarbonImmutable::parse($tenancy->start_date->toDateString());
        $end = CarbonImmutable::parse($tenancy->end_date->toDateString());
        if ($asOf->lt($start) || $asOf->gt($end)) {
            return null;
        }

        $months = (($asOf->year - $start->year) * 12) + $asOf->month - $start->month;
        $candidatePeriodStart = $start->addMonthsNoOverflow($months);
        if ($candidatePeriodStart->gt($asOf)) {
            $periodStart = $start->addMonthsNoOverflow($months - 1);
            $nextPeriodStart = $candidatePeriodStart;
        } else {
            $periodStart = $candidatePeriodStart;
            $nextPeriodStart = $start->addMonthsNoOverflow($months + 1);
        }
        $periodEnd = $nextPeriodStart->subDay()->min($end);

        if ($periodStart->gt($end)) {
            return null;
        }

        return Invoice::query()->firstOrCreate(
            ['idempotency_key' => 'tenancy:'.$tenancy->id.':rent:'.$periodStart->toDateString()],
            [
                'tenancy_id' => $tenancy->id,
                'organization_id' => $tenancy->organization_id,
                'tenant_id' => $tenancy->tenant_id,
                'type' => InvoiceType::Rent,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_date' => $periodStart->toDateString(),
                'amount' => $tenancy->monthly_rent,
                'status' => InvoiceStatus::Unpaid,
            ],
        );
    }
}
