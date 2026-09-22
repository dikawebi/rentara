<?php

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\Media;
use App\Models\PropertyAssignment;
use App\Models\User;
use App\Models\Workspace;
use App\Enums\PropertyStatus;

class MediaPolicy
{
    public function view(User $user, Media $media): bool
    {
        return $this->parentIsValid($media) && $this->allowed($user, $media->workspace, $this->propertyId($media));
    }

    public function create(User $user, Workspace $workspace, ?int $propertyId = null): bool
    {
        $role = $this->role($user, $workspace);
        return $role === WorkspaceMemberRole::Owner || ($role === WorkspaceMemberRole::Manager && $this->assigned($user, $workspace->id, $propertyId));
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->parentIsValid($media) && $this->create($user, $media->workspace, $this->propertyId($media));
    }

    private function allowed(User $user, Workspace $workspace, ?int $propertyId): bool
    {
        $role = $this->role($user, $workspace);
        return $role === WorkspaceMemberRole::Owner
            || (in_array($role, [WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true) && $this->assigned($user, $workspace->id, $propertyId));
    }

    private function assigned(User $user, int $workspaceId, ?int $propertyId): bool
    {
        return $propertyId !== null && PropertyAssignment::where('workspace_id', $workspaceId)
            ->where('property_id', $propertyId)
            ->where('user_id', $user->id)
            ->whereHas('property', fn ($query) => $query->where('workspace_id', $workspaceId)->where('status', PropertyStatus::Active->value))
            ->exists();
    }

    private function propertyId(Media $media): ?int
    {
        return $media->property_id ?? $media->unit?->property_id;
    }

    private function parentIsValid(Media $media): bool
    {
        $property = $media->property_id ? $media->property : $media->unit?->property;
        return $property !== null && $property->workspace_id === $media->workspace_id && $property->status === PropertyStatus::Active && ! $property->trashed()
            && (! $media->unit_id || ($media->unit && $media->unit->workspace_id === $media->workspace_id && ! $media->unit->trashed()));
    }

    private function role(User $user, Workspace $workspace): ?WorkspaceMemberRole
    {
        if ($user->platform_role === PlatformRole::SuperAdmin) return null;
        return $workspace->members()->where('user_id', $user->id)->where('status', UserStatus::Active->value)->first()?->role;
    }
}
