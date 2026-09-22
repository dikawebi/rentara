<?php

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class WorkspaceMemberPolicy
{
    public function view(User $user, WorkspaceMember $member): bool
    {
        return ! $this->isSuperAdmin($user) && $this->role($user, $member->workspace) !== null;
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return ! $this->isSuperAdmin($user) && in_array($this->role($user, $workspace), [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager], true);
    }

    public function update(User $user, WorkspaceMember $member): bool
    {
        return $this->allowed($user, $member);
    }

    public function delete(User $user, WorkspaceMember $member): bool
    {
        return $this->allowed($user, $member);
    }

    public function changeRole(User $user, WorkspaceMember $member): bool
    {
        return $this->allowed($user, $member);
    }

    public function changeStatus(User $user, WorkspaceMember $member): bool
    {
        return $this->allowed($user, $member);
    }

    private function allowed(User $user, WorkspaceMember $member): bool
    {
        if ($this->isSuperAdmin($user)) {
            return false;
        }
        if ($member->isCanonicalOwner()) {
            return false;
        }
        $actor = $this->role($user, $member->workspace);

        return $actor === WorkspaceMemberRole::Owner || ($actor === WorkspaceMemberRole::Manager && $member->role === WorkspaceMemberRole::Staff);
    }

    private function role(User $user, Workspace $workspace): ?WorkspaceMemberRole
    {
        return $workspace->members()->where('user_id', $user->id)->where('status', UserStatus::Active->value)->first()?->role;
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->platform_role === PlatformRole::SuperAdmin;
    }
}
