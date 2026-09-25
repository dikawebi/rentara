<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\RentalApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpsertRentalAgreementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('terms_snapshot'))) {
            $decoded = json_decode($this->input('terms_snapshot'), true);
            if (is_array($decoded)) {
                $this->merge(['terms_snapshot' => $decoded]);
            }
        }
    }

    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $application = $this->route('application');

        return $organization instanceof Organization
            && $application instanceof RentalApplication
            && $application->listing?->property?->organization_id === $organization->id
            && ($this->user()?->can('manageAgreements', $organization) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'terms_snapshot' => ['required', 'array', 'min:1'],
            'contract_file' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ];
    }
}
