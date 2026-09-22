<?php

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Workspace;

class UnitTypePolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace) !== null;
    }

    public function view(User $user, UnitType $unitType): bool
    {
        return $this->role($user, $this->workspaceOf($unitType)) !== null;
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager], true);
    }

    public function update(User $user, UnitType $unitType): bool
    {
        return in_array($this->role($user, $this->workspaceOf($unitType)), [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager], true);
    }

    public function delete(User $user, UnitType $unitType): bool
    {
        return in_array($this->role($user, $this->workspaceOf($unitType)), [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager], true);
    }

    public function restore(User $user, UnitType $unitType): bool
    {
        return in_array($this->role($user, $this->workspaceOf($unitType)), [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager], true);
    }

    public function forceDelete(User $user, UnitType $unitType): bool
    {
        return false;
    }

    private function workspaceOf(UnitType $unitType): Workspace
    {
        if ($unitType->relationLoaded('workspace') && $unitType->workspace) {
            return $unitType->workspace;
        }

        return Workspace::findOrFail($unitType->workspace_id);
    }

    private function role(User $user, Workspace $workspace): ?WorkspaceMemberRole
    {
        if ($user->platform_role === PlatformRole::SuperAdmin) {
            return null;
        }

        return $workspace->members()->where('user_id', $user->id)->where('status', UserStatus::Active->value)->first()?->role;
    }
}
