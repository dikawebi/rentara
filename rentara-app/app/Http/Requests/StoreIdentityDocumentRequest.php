<?php

namespace App\Http\Requests;

use App\Models\RentalApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdentityDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application instanceof RentalApplication
            && $application->applicant_id === $this->user()?->id
            && $this->user()?->hasVerifiedEmail() === true
            && $application->status->isActive();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $application = $this->route('application');
        $documentRules = ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        $uploadedDocument = $this->file('document');

        if ($uploadedDocument !== null && str_starts_with((string) $uploadedDocument->getMimeType(), 'image/')) {
            $documentRules[] = 'dimensions:min_width=320,min_height=200,max_width=3000,max_height=3000';
        }

        return [
            'document_type' => [
                'required',
                'string',
                Rule::in($application instanceof RentalApplication ? $application->requiredIdentityDocumentTypes() : []),
            ],
            'document' => $documentRules,
        ];
    }
}
