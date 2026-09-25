<?php

namespace App\Console\Commands;

use App\Models\Tenancy;
use App\Services\TenancyExpiryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EndExpiredTenancies extends Command
{
    protected $signature = 'tenancies:end-expired';

    protected $description = 'End active tenancies whose end date has passed';

    public function handle(TenancyExpiryService $expiryService): int
    {
        $ended = 0;
        Tenancy::query()
            ->where('status', 'active')
            ->whereDate('end_date', '<', today())
            ->pluck('id')
            ->each(function (int $id) use ($expiryService, &$ended): void {
                DB::transaction(function () use ($id, $expiryService, &$ended): void {
                    $tenancy = Tenancy::query()->lockForUpdate()->find($id);
                    if ($tenancy !== null && $expiryService->end($tenancy)) {
                        $ended++;
                    }
                }, attempts: 3);
            });

        $this->info("Ended {$ended} tenancy(ies).");

        return self::SUCCESS;
    }
}
