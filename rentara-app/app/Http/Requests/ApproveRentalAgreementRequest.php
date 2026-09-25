<?php

namespace App\Http\Requests;

use App\Models\RentalApplication;
use Illuminate\Foundation\Http\FormRequest;

class ApproveRentalAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application instanceof RentalApplication
            && $application->applicant_id === $this->user()?->id;
    }

    /** @return array<string, array<string>|string> */
    public function rules(): array
    {
        return [];
    }
}
