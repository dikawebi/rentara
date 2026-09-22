<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberRole;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\User;

class PropertyAssignmentPolicy extends PropertyScopedPolicy
{
    public function viewAny(User $user, Property $property): bool
    {
        return $this->canViewProperty($user, $property);
    }

    public function view(User $user, PropertyAssignment $assignment): bool
    {
        return $this->canViewProperty($user, $this->propertyOf($assignment));
    }

    public function create(User $user, Property $property): bool
    {
        return $this->role($user, $this->workspaceOfProperty($property)) === WorkspaceMemberRole::Owner;
    }

    public function update(User $user, PropertyAssignment $assignment): bool
    {
        return false;
    }

    public function delete(User $user, PropertyAssignment $assignment): bool
    {
        return $this->role($user, $this->workspaceOfProperty($this->propertyOf($assignment))) === WorkspaceMemberRole::Owner;
    }

    public function restore(User $user, PropertyAssignment $assignment): bool
    {
        return false;
    }

    public function forceDelete(User $user, PropertyAssignment $assignment): bool
    {
        return false;
    }

    private function propertyOf(PropertyAssignment $assignment): Property
    {
        if ($assignment->relationLoaded('property') && $assignment->property) {
            return $assignment->property;
        }

        return Property::findOrFail($assignment->property_id);
    }
}
