<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\RentalApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIdentityDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_status' => ['required', 'string', Rule::in(['accepted', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'required_if:review_status,rejected', 'min:10', 'max:1000'],
        ];
    }
}
