<?php

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;

class TenantPolicy
{
    private function role(User $user, Workspace $workspace): ?WorkspaceMemberRole
    {
        if ($user->platform_role === PlatformRole::SuperAdmin) return null;
        return $workspace->members()->where('user_id', $user->id)
            ->where('status', UserStatus::Active->value)->first()?->role;
    }

    private function workspace(User $user, Tenant $tenant): ?Workspace
    {
        return Workspace::find($tenant->workspace_id);
    }

    private function canForUnit(User $user, Workspace $workspace, ?Unit $unit, bool $manage): bool
    {
        $role = $this->role($user, $workspace);
        if ($role === WorkspaceMemberRole::Owner) return true;
        if (! in_array($role, [WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true) || ! $unit) return false;
        $assigned = $unit->property && $unit->property->assignments()->where('user_id', $user->id)->exists();
        return $assigned && ($manage ? $role === WorkspaceMemberRole::Manager : true);
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace) !== null;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        $workspace = $this->workspace($user, $tenant);
        if (! $workspace) return false;
        return $this->canForUnit($user, $workspace, $tenant->unit, false) ||
            ($this->role($user, $workspace) === WorkspaceMemberRole::Owner);
    }

    public function createForUnit(User $user, Workspace $workspace, ?Unit $unit): bool
    { return $this->canForUnit($user, $workspace, $unit, true); }

    public function updateForUnit(User $user, Tenant $tenant, ?Unit $unit): bool
    {
        $workspace = $this->workspace($user, $tenant);
        return $workspace ? $this->canForUnit($user, $workspace, $tenant->unit, true)
            && $this->canForUnit($user, $workspace, $unit, true) : false;
    }

    public function updateForUnits(User $user, Tenant $tenant, ?Unit $currentUnit, ?Unit $targetUnit): bool
    {
        $workspace = $this->workspace($user, $tenant);
        return $workspace ? $this->canForUnit($user, $workspace, $currentUnit, true)
            && ($targetUnit === null || $this->canForUnit($user, $workspace, $targetUnit, true)) : false;
    }

    public function create(User $user, Tenant $tenant): bool
    { return $this->createForUnit($user, Workspace::findOrFail($tenant->workspace_id), $tenant->unit); }
    public function update(User $user, Tenant $tenant): bool
    { return $this->updateForUnit($user, $tenant, $tenant->unit); }
    public function delete(User $user, Tenant $tenant): bool { return $this->update($user, $tenant); }
    public function restore(User $user, Tenant $tenant): bool { return $this->update($user, $tenant); }
    public function forceDelete(User $user, Tenant $tenant): bool { return false; }
}
