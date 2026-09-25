<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\RentalApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRentalApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $application = $this->route('application');

        return $organization instanceof Organization
            && $application instanceof RentalApplication
            && $application->listing?->property?->organization_id === $organization->id
            && ($this->user()?->can('manageApplications', $organization) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['under_review', 'info_requested', 'rejected', 'approved'])],
            'notes' => ['nullable', 'string', 'required_if:status,info_requested,rejected', 'min:10', 'max:2000'],
        ];
    }
}
