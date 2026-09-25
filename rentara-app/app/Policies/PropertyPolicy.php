<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\OrganizationRole;

class PropertyPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->organizationRole($user, $organization) !== null;
    }

    public function view(User $user, Property $property): bool
    {
        if ($user->is_platform_admin === true) {
            return true;
        }

        $role = $user->organizationMemberships()
            ->where('organization_id', $property->organization_id)
            ->whereNotNull('accepted_at')
            ->first()?->role;

        return $role !== null;
    }

    public function create(User $user, Organization $organization): bool
    {
        return in_array($this->organizationRole($user, $organization), [
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        ], true);
    }

    public function update(User $user, Property $property): bool
    {
        $role = $user->organizationMemberships()
            ->where('organization_id', $property->organization_id)
            ->whereNotNull('accepted_at')
            ->first()?->role;

        return in_array($role, [OrganizationRole::Owner, OrganizationRole::Manager], true);
    }

    public function delete(User $user, Property $property): bool
    {
        $role = $user->organizationMemberships()
            ->where('organization_id', $property->organization_id)
            ->whereNotNull('accepted_at')
            ->first()?->role;

        return $role === OrganizationRole::Owner;
    }

    private function organizationRole(User $user, Organization $organization): ?OrganizationRole
    {
        $role = $user->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->whereNotNull('accepted_at')
            ->first()?->role;

        if ($role instanceof OrganizationRole || $role === null) {
            return $role;
        }

        return OrganizationRole::from($role);
    }
}
