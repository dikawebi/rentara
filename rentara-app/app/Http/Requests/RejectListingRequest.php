<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectListingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_platform_admin === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_notes' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
