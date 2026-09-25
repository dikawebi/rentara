<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PropertyUnitController extends Controller
{
    public function store(StoreUnitRequest $request, Organization $organization, Property $property): RedirectResponse
    {
        DB::transaction(function () use ($request, $organization, $property): void {
            $lockedProperty = Property::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($property->id);

            Gate::authorize('update', $lockedProperty);
            $lockedProperty->units()->create($request->validated());
            $lockedProperty->invalidateListingReview();
        });

        return back()->with('status', 'unit-created');
    }

    public function update(
        UpdateUnitRequest $request,
        Organization $organization,
        Property $property,
        Unit $unit,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $organization, $property, $unit): void {
            $lockedProperty = Property::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($property->id);
            $lockedUnit = $lockedProperty->units()->lockForUpdate()->findOrFail($unit->id);

            Gate::authorize('update', $lockedProperty);
            $lockedUnit->update($request->validated());
            $lockedProperty->invalidateListingReview();
        });

        return back()->with('status', 'unit-updated');
    }

    public function destroy(Organization $organization, Property $property, Unit $unit): RedirectResponse
    {
        DB::transaction(function () use ($organization, $property, $unit): void {
            $lockedProperty = Property::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->findOrFail($property->id);
            $lockedUnit = $lockedProperty->units()->lockForUpdate()->findOrFail($unit->id);

            Gate::authorize('update', $lockedProperty);
            $lockedUnit->delete();
            $lockedProperty->invalidateListingReview();
        });

        return back()->with('status', 'unit-deleted');
    }
}
