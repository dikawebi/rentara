<?php

namespace App\Http\Requests;

use App\ComplaintStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ComplaintStatus::class)], 'assigned_to' => ['nullable', 'integer'], 'resolution_notes' => ['nullable', 'string', 'max:10000']];
    }
}
