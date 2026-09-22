<?php

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace) !== null;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager], true);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->update($user, $workspace);
    }

    private function role(User $user, Workspace $workspace): ?WorkspaceMemberRole
    {
        if ($user->platform_role === PlatformRole::SuperAdmin) {
            return null;
        }

        return $workspace->members()->where('user_id', $user->id)->where('status', UserStatus::Active->value)->first()?->role;
    }
}
