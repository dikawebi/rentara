<?php

namespace App\Console\Commands;

use App\Models\Tenancy;
use App\Services\InvoiceGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateRentInvoices extends Command
{
    protected $signature = 'invoices:generate-rent';

    protected $description = 'Generate the current monthly rent invoice for active tenancies';

    public function handle(InvoiceGenerationService $invoiceGenerationService): int
    {
        Tenancy::query()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->each(function (Tenancy $tenancy) use ($invoiceGenerationService): void {
                $invoiceGenerationService->generateRentFor($tenancy, CarbonImmutable::today());
            });

        return self::SUCCESS;
    }
}
