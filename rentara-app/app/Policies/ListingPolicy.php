<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use App\OrganizationRole;

class ListingPolicy
{
    public function manage(User $user, Listing $listing): bool
    {
        return in_array($user->organizationMemberships()
            ->where('organization_id', $listing->property->organization_id)
            ->whereNotNull('accepted_at')
            ->first()?->role, [OrganizationRole::Owner, OrganizationRole::Manager], true);
    }

    public function review(User $user, ?Listing $listing = null): bool
    {
        return $user->is_platform_admin === true;
    }
}
