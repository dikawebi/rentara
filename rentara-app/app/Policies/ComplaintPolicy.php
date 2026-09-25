<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;
use App\OrganizationRole;

class ComplaintPolicy
{
    public function view(User $user, Complaint $complaint): bool
    {
        return $this->isTenant($user, $complaint) || $this->isManager($user, $complaint->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->tenancies()->where('status', 'active')->exists();
    }

    public function manage(User $user, Complaint $complaint): bool
    {
        return $this->isManager($user, $complaint->organization_id);
    }

    public function tenantComment(User $user, Complaint $complaint): bool
    {
        return $this->isTenant($user, $complaint);
    }

    private function isTenant(User $user, Complaint $complaint): bool
    {
        return $complaint->tenant_id === $user->id;
    }

    private function isManager(User $user, int $organizationId): bool
    {
        return $user->organizationMemberships()->where('organization_id', $organizationId)->whereNotNull('accepted_at')->whereIn('role', [OrganizationRole::Owner->value, OrganizationRole::Manager->value])->exists();
    }
}
