<?php

namespace App\Http\Requests;

use App\ComplaintCategory;
use App\ComplaintPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['tenancy_id' => ['required', 'integer'], 'category' => ['required', Rule::enum(ComplaintCategory::class)], 'title' => ['required', 'string', 'max:160'], 'description' => ['required', 'string', 'max:10000'], 'priority' => ['required', Rule::enum(ComplaintPriority::class)]];
    }
}
