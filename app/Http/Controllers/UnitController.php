<?php

namespace App\Http\Controllers;

use App\Enums\RentalPeriod;
use App\Enums\UnitStatus;
use App\Models\Building;
use App\Models\Block;
use App\Models\Floor;
use App\Models\Property;
use App\Models\Unit;
use App\Models\UnitType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    private function findProperty(Request $request, int $propertyId): Property
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;

        return Property::where('workspace_id', $workspaceId)->findOrFail($propertyId);
    }

    private function find(Property $property, int $id, bool $withTrashed = false): Unit
    {
        return Unit::where('workspace_id', $property->workspace_id)
            ->where('property_id', $property->id)
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Property $property, ?int $ignoreId = null): array
    {
        return [
            'unit_number' => [
                'required', 'string', 'max:50',
                Rule::unique('units', 'unit_number')
                    ->where(fn ($query) => $query->where('property_id', $property->id))
                    ->ignore($ignoreId),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'building_id' => [
                'nullable', 'integer',
                Rule::exists('buildings', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($property) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $belongs = Building::where('id', $value)->where('property_id', $property->id)->exists();
                    if (! $belongs) {
                        $fail('Gedung yang dipilih tidak termasuk dalam properti ini.');
                    }
                },
            ],
            'floor_id' => [
                'nullable', 'integer',
                Rule::exists('floors', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($property) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $belongs = Floor::where('id', $value)->where('property_id', $property->id)->exists();
                    if (! $belongs) {
                        $fail('Lantai yang dipilih tidak termasuk dalam properti ini.');
                    }
                },
            ],
            'block_id' => [
                'nullable', 'integer',
                Rule::exists('blocks', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($property) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $belongs = Block::where('id', $value)->where('property_id', $property->id)->exists();
                    if (! $belongs) {
                        $fail('Blok yang dipilih tidak termasuk dalam properti ini.');
                    }
                },
            ],
            'unit_type_id' => [
                'nullable', 'integer',
                Rule::exists('unit_types', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($property) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $belongs = UnitType::where('id', $value)->where('workspace_id', $property->workspace_id)->exists();
                    if (! $belongs) {
                        $fail('Tipe unit yang dipilih tidak termasuk dalam ruang kerja ini.');
                    }
                },
            ],
            'area' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'rental_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'rental_period' => ['required', Rule::enum(RentalPeriod::class)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', Rule::enum(UnitStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'unit_number.unique' => 'Nomor unit sudah digunakan pada properti ini (termasuk data yang telah dihapus).',
        ];
    }

    private function options(Property $property): array
    {
        return [
            'buildings' => Building::where('property_id', $property->id)->orderBy('sort_order')->orderBy('name')->get(),
            'floors' => Floor::where('property_id', $property->id)->orderBy('sort_order')->orderBy('name')->get(),
            'blocks' => Block::where('property_id', $property->id)->orderBy('sort_order')->orderBy('name')->get(),
            'unitTypes' => UnitType::where('workspace_id', $property->workspace_id)->orderBy('name')->get(),
        ];
    }

    public function index(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('viewAny', [Unit::class, $property]);

        $units = Unit::where('property_id', $property->id)
            ->with(['building', 'floor', 'block', 'unitType'])
            ->orderBy('unit_number')
            ->paginate(15)->withQueryString();

        return response()->view('units.index', array_merge([
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
            'units' => $units,
        ]));
    }

    public function create(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('create', [Unit::class, $property]);

        return response()->view('units.create', array_merge([
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
        ], $this->options($property)));
    }

    public function store(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('create', [Unit::class, $property]);

        $data = $request->validate($this->rules($property), $this->messages());
        $data['workspace_id'] = $property->workspace_id;
        $data['property_id'] = $property->id;
        $data['capacity'] ??= 1;
        $data['status'] ??= UnitStatus::Available->value;

        $unit = Unit::create($data);

        return redirect()->route('app.properties.units.index', $property)->with('status', 'Unit berhasil ditambahkan.');
    }

    public function edit(Request $request, int $property, int $unit)
    {
        $property = $this->findProperty($request, $property);
        $unit = $this->find($property, $unit);
        $this->authorize('update', $unit);

        return response()->view('units.edit', array_merge([
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
            'unit' => $unit,
        ], $this->options($property)));
    }

    public function update(Request $request, int $property, int $unit)
    {
        $property = $this->findProperty($request, $property);
        $unit = $this->find($property, $unit);
        $this->authorize('update', $unit);

        $data = $request->validate($this->rules($property, $unit->id), $this->messages());

        $unit->update($data);

        return redirect()->route('app.properties.units.index', $property)->with('status', 'Perubahan unit berhasil disimpan.');
    }

    public function destroy(Request $request, int $property, int $unit)
    {
        $property = $this->findProperty($request, $property);
        $unit = $this->find($property, $unit);
        $this->authorize('delete', $unit);

        $unit->delete();

        return redirect()->route('app.properties.units.index', $property)->with('status', 'Unit berhasil dihapus.');
    }

    public function restore(Request $request, int $property, int $unit)
    {
        $property = $this->findProperty($request, $property);
        $unit = $this->find($property, $unit, true);
        $this->authorize('restore', $unit);

        if ($unit->trashed()) {
            $conflict = Unit::where('property_id', $property->id)
                ->where('unit_number', $unit->unit_number)
                ->whereNull('deleted_at')
                ->exists();

            abort_if($conflict, 422, 'Unit "'.$unit->unit_number.'" tidak dapat dipulihkan karena nomor tersebut sudah digunakan oleh data aktif.');

            $unit->restore();
        }

        return redirect()->route('app.properties.units.index', $property)->with('status', 'Unit berhasil dipulihkan.');
    }
}
