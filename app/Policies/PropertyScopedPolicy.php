<?php

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\User;
use App\Models\Workspace;

abstract class PropertyScopedPolicy
{
    protected function workspaceOfProperty(Property $property): Workspace
    {
        if ($property->relationLoaded('workspace') && $property->workspace) {
            return $property->workspace;
        }

        return Workspace::findOrFail($property->workspace_id);
    }

    protected function role(User $user, Workspace $workspace): ?WorkspaceMemberRole
    {
        if ($user->platform_role === PlatformRole::SuperAdmin) {
            return null;
        }

        return $workspace->members()->where('user_id', $user->id)->where('status', UserStatus::Active->value)->first()?->role;
    }

    protected function isAssigned(User $user, Property $property): bool
    {
        return PropertyAssignment::where('property_id', $property->id)->where('user_id', $user->id)->exists();
    }

    protected function canViewProperty(User $user, Property $property): bool
    {
        $role = $this->role($user, $this->workspaceOfProperty($property));

        if ($role === WorkspaceMemberRole::Owner) {
            return true;
        }

        if (! in_array($role, [WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true)) {
            return false;
        }

        return $this->isAssigned($user, $property);
    }

    protected function canManageProperty(User $user, Property $property): bool
    {
        $role = $this->role($user, $this->workspaceOfProperty($property));

        if ($role === WorkspaceMemberRole::Owner) {
            return true;
        }

        if ($role !== WorkspaceMemberRole::Manager) {
            return false;
        }

        return $this->isAssigned($user, $property);
    }
}
