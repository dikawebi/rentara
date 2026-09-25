<?php

namespace Database\Factories;

use App\Models\RentalAgreement;
use App\Models\RentalApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentalAgreement> */
class RentalAgreementFactory extends Factory
{
    protected $model = RentalAgreement::class;

    public function definition(): array
    {
        return [
            'rental_application_id' => RentalApplication::factory(),
            'version' => 1,
            'terms_snapshot' => ['monthly_rent' => 0],
            'contract_storage_path' => 'agreements/test.pdf',
            'contract_original_name' => 'contract.pdf',
            'contract_mime_type' => 'application/pdf',
            'contract_byte_size' => 0,
        ];
    }
}
