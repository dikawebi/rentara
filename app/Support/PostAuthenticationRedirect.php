<?php

namespace App\Support;

use App\Enums\PlatformRole;
use App\Models\User;

class PostAuthenticationRedirect
{
    public function for(User $user): string
    {
        if ($user->platform_role === PlatformRole::SuperAdmin) {
            return route('admin.dashboard', absolute: false);
        }

        return route('app.dashboard', absolute: false);
    }

    public function isPlatformOnly(User $user): bool
    {
        return $user->platform_role === PlatformRole::SuperAdmin;
    }
}
