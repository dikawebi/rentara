<?php

namespace App\Policies;

use App\Models\Block;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Property;
use App\Models\User;

class StructurePolicy extends PropertyScopedPolicy
{
    public function viewAny(User $user, Property $property): bool
    {
        return $this->canViewProperty($user, $property);
    }

    public function view(User $user, Building|Floor|Block $structure): bool
    {
        return $this->canViewProperty($user, $this->propertyOf($structure));
    }

    public function create(User $user, Property $property): bool
    {
        return $this->canManageProperty($user, $property);
    }

    public function update(User $user, Building|Floor|Block $structure): bool
    {
        return $this->canManageProperty($user, $this->propertyOf($structure));
    }

    public function delete(User $user, Building|Floor|Block $structure): bool
    {
        return $this->canManageProperty($user, $this->propertyOf($structure));
    }

    public function restore(User $user, Building|Floor|Block $structure): bool
    {
        return $this->canManageProperty($user, $this->propertyOf($structure));
    }

    public function forceDelete(User $user, Building|Floor|Block $structure): bool
    {
        return false;
    }

    private function propertyOf(Building|Floor|Block $structure): Property
    {
        if ($structure->relationLoaded('property') && $structure->property) {
            return $structure->property;
        }

        return Property::findOrFail($structure->property_id);
    }
}
