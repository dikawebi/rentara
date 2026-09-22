<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitAmenityController extends Controller
{
    private function unit(Request $request, int $propertyId, int $unitId): Unit
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;
        $property = Property::where('workspace_id', $workspaceId)->findOrFail($propertyId);
        abort_unless($property->status->value === 'active' && ! $property->trashed(), 404);
        $unit = Unit::where('workspace_id', $workspaceId)->where('property_id', $property->id)->findOrFail($unitId);
        abort_unless(! $unit->trashed(), 404);
        return $unit;
    }

    public function edit(Request $request, int $property, int $unit)
    {
        $unit = $this->unit($request, $property, $unit);
        $this->authorize('view', $unit);
        return view('units.amenities', ['workspace' => $request->attributes->get('currentWorkspace'), 'property' => $unit->property, 'unit' => $unit->load('amenities'), 'amenities' => Amenity::where('workspace_id', $unit->workspace_id)->orderBy('name')->get()]);
    }

    public function update(Request $request, int $property, int $unit)
    {
        $unit = $this->unit($request, $property, $unit);
        $this->authorize('update', $unit);
        $data = $request->validate(['amenity_ids' => ['nullable', 'array'], 'amenity_ids.*' => ['integer', 'distinct']]);
        $ids = Amenity::where('workspace_id', $unit->workspace_id)->whereNull('deleted_at')->whereIn('id', $data['amenity_ids'] ?? [])->pluck('id')->all();
        abort_if(count($ids) !== count($data['amenity_ids'] ?? []), 422, 'Fasilitas tidak termasuk dalam ruang kerja ini.');
        DB::transaction(fn () => $unit->amenities()->syncWithPivotValues($ids, ['workspace_id' => $unit->workspace_id, 'created_by' => $request->user()->id]));
        return redirect()->route('app.properties.units.amenities.edit', [$unit->property, $unit])->with('status', 'Fasilitas unit berhasil disimpan.');
    }
}
