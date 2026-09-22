<?php

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\User;
use App\Models\Workspace;

class PropertyPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace) !== null;
    }

    public function view(User $user, Property $property): bool
    {
        $role = $this->role($user, $this->workspaceOf($property));

        if ($role === WorkspaceMemberRole::Owner) {
            return true;
        }

        if (! in_array($role, [WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true)) {
            return false;
        }

        return $this->isAssigned($user, $property);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager], true);
    }

    public function update(User $user, Property $property): bool
    {
        $role = $this->role($user, $this->workspaceOf($property));

        if ($role === WorkspaceMemberRole::Owner) {
            return true;
        }

        if ($role !== WorkspaceMemberRole::Manager) {
            return false;
        }

        return $this->isAssigned($user, $property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->role($user, $this->workspaceOf($property)) === WorkspaceMemberRole::Owner;
    }

    public function restore(User $user, Property $property): bool
    {
        return $this->role($user, $this->workspaceOf($property)) === WorkspaceMemberRole::Owner;
    }

    public function forceDelete(User $user, Property $property): bool
    {
        return false;
    }

    private function workspaceOf(Property $property): Workspace
    {
        if ($property->relationLoaded('workspace') && $property->workspace) {
            return $property->workspace;
        }

        return Workspace::findOrFail($property->workspace_id);
    }

    private function role(User $user, Workspace $workspace): ?WorkspaceMemberRole
    {
        if ($user->platform_role === PlatformRole::SuperAdmin) {
            return null;
        }

        return $workspace->members()->where('user_id', $user->id)->where('status', UserStatus::Active->value)->first()?->role;
    }

    private function isAssigned(User $user, Property $property): bool
    {
        return PropertyAssignment::where('property_id', $property->id)->where('user_id', $user->id)->exists();
    }
}
