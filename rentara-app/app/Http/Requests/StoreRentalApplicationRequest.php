<?php

namespace App\Http\Requests;

use App\ListingStatus;
use App\Models\Listing;
use App\UnitStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRentalApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing
            && $this->user()?->hasVerifiedEmail() === true
            && $listing->status === ListingStatus::Approved;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $listing = $this->route('listing');

        return [
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query
                    ->where('property_id', $listing->property_id)
                    ->where('status', UnitStatus::Available->value)),
            ],
            'requested_move_in' => ['required', 'date', 'after_or_equal:today'],
            'requested_duration_months' => ['required', 'integer', 'between:1,60'],
            'applicant_note' => ['nullable', 'string', 'max:2000'],
            'privacy_consent' => ['accepted'],
        ];
    }
}
