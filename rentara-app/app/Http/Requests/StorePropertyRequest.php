<?php

namespace App\Http\Requests;

use App\IdentityDocumentType;
use App\Models\Organization;
use App\Models\Property;
use App\PropertyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization && Gate::allows('create', [Property::class, $organization]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'property_type' => ['required', 'string', Rule::in(array_column(PropertyType::cases(), 'value'))],
            'description' => ['nullable', 'string', 'max:5000'],
            'full_address' => ['required', 'string', 'max:1000'],
            'district' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'identity_document_requirements' => ['nullable', 'array', 'max:4'],
            'identity_document_requirements.*' => ['required', 'string', Rule::in(array_column(IdentityDocumentType::cases(), 'value'))],
            'booking_expiry_days' => ['nullable', 'integer', 'between:1,30'],
        ];
    }
}
