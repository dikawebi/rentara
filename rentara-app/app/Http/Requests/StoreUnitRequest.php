<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property && Gate::allows('update', $property);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('units', 'name')->where(fn ($query) => $query->where('property_id', $this->route('property')->id)),
            ],
            'capacity' => ['required', 'integer', 'between:1,30'],
            'monthly_price' => ['required', 'integer', 'between:1,1000000000'],
        ];
    }
}
