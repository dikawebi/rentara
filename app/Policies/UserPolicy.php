<?php

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Models\User;

class UserPolicy
{
    /** Platform access is self-scoped and grants no workspace authority. */
    public function viewPlatformDashboard(User $user, User $subject): bool
    {
        return $user->is($subject) && $user->platform_role === PlatformRole::SuperAdmin;
    }
}
