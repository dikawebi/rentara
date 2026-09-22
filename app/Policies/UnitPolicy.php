<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy extends PropertyScopedPolicy
{
    public function viewAny(User $user, Property $property): bool
    {
        return $this->canViewProperty($user, $property);
    }

    public function view(User $user, Unit $unit): bool
    {
        return $this->canViewProperty($user, $this->propertyOf($unit));
    }

    public function create(User $user, Property $property): bool
    {
        return $this->canManageProperty($user, $property);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->canManageProperty($user, $this->propertyOf($unit));
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->canManageProperty($user, $this->propertyOf($unit));
    }

    public function restore(User $user, Unit $unit): bool
    {
        return $this->canManageProperty($user, $this->propertyOf($unit));
    }

    public function forceDelete(User $user, Unit $unit): bool
    {
        return false;
    }

    private function propertyOf(Unit $unit): Property
    {
        if ($unit->relationLoaded('property') && $unit->property) {
            return $unit->property;
        }

        return Property::findOrFail($unit->property_id);
    }
}
