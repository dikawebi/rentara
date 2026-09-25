<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\OrganizationRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InviteOrganizationMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && ($this->user()?->can('inviteMembers', $organization) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $ownerCanInviteManagers = $organization instanceof Organization
            && $this->user()?->organizationMemberships()
                ->where('organization_id', $organization->id)
                ->where('role', OrganizationRole::Owner->value)
                ->whereNotNull('accepted_at')
                ->exists();

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in($ownerCanInviteManagers
                ? [OrganizationRole::Manager->value, OrganizationRole::Staff->value]
                : [OrganizationRole::Staff->value])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }
}
