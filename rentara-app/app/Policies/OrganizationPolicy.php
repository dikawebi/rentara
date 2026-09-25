<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\OrganizationRole;

class OrganizationPolicy
{
    public function viewMembers(User $user, Organization $organization): bool
    {
        return in_array($this->membershipRole($user, $organization), [
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        ], true);
    }

    public function viewApplications(User $user, Organization $organization): bool
    {
        return $this->hasManagementRole($user, $organization);
    }

    public function manageApplications(User $user, Organization $organization): bool
    {
        return $this->hasManagementRole($user, $organization);
    }

    public function manageAgreements(User $user, Organization $organization): bool
    {
        return $this->hasManagementRole($user, $organization);
    }

    public function manageBookings(User $user, Organization $organization): bool
    {
        return $this->hasManagementRole($user, $organization);
    }

    public function manageInvoices(User $user, Organization $organization): bool
    {
        return $this->hasManagementRole($user, $organization);
    }

    public function updatePolicy(User $user, Organization $organization): bool
    {
        return $this->membershipRole($user, $organization) === OrganizationRole::Owner;
    }

    public function inviteMembers(User $user, Organization $organization): bool
    {
        return in_array($this->membershipRole($user, $organization), [
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        ], true);
    }

    public function inviteRole(User $user, Organization $organization, OrganizationRole $role): bool
    {
        $membershipRole = $this->membershipRole($user, $organization);

        return $role !== OrganizationRole::Owner
            && ($membershipRole === OrganizationRole::Owner
                || ($membershipRole === OrganizationRole::Manager && $role === OrganizationRole::Staff));
    }

    public function removeMember(User $user, Organization $organization, OrganizationMembership $membership): bool
    {
        if ($membership->organization_id !== $organization->id || $membership->accepted_at === null) {
            return false;
        }

        $actorRole = $this->membershipRole($user, $organization);

        return ($actorRole === OrganizationRole::Owner && $membership->role !== OrganizationRole::Owner)
            || ($actorRole === OrganizationRole::Manager && $membership->role === OrganizationRole::Staff);
    }

    public function revokeInvitation(User $user, Organization $organization, OrganizationInvitation $invitation): bool
    {
        if ($invitation->organization_id !== $organization->id || $invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            return false;
        }

        $actorRole = $this->membershipRole($user, $organization);

        return $actorRole === OrganizationRole::Owner
            || ($actorRole === OrganizationRole::Manager && $invitation->role === OrganizationRole::Staff);
    }

    private function membershipRole(User $user, Organization $organization): ?OrganizationRole
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

    private function hasManagementRole(User $user, Organization $organization): bool
    {
        return in_array($this->membershipRole($user, $organization), [
            OrganizationRole::Owner,
            OrganizationRole::Manager,
        ], true);
    }
}
