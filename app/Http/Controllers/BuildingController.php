<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuildingController extends Controller
{
    private function findProperty(Request $request, int $propertyId): Property
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;

        return Property::where('workspace_id', $workspaceId)->findOrFail($propertyId);
    }

    private function find(Property $property, int $id, bool $withTrashed = false): Building
    {
        return Building::where('workspace_id', $property->workspace_id)
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
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('buildings', 'name')
                    ->where(fn ($query) => $query->where('property_id', $property->id))
                    ->ignore($ignoreId),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:16777215'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.unique' => 'Nama gedung sudah digunakan pada properti ini (termasuk data yang telah dihapus).',
        ];
    }

    public function index(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('viewAny', [Building::class, $property]);

        $buildings = Building::where('property_id', $property->id)
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(15)->withQueryString();

        return response()->view('buildings.index', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
            'buildings' => $buildings,
        ]);
    }

    public function create(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('create', [Building::class, $property]);

        return response()->view('buildings.create', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
        ]);
    }

    public function store(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('create', [Building::class, $property]);

        $data = $request->validate($this->rules($property), $this->messages());
        $data['workspace_id'] = $property->workspace_id;
        $data['property_id'] = $property->id;
        $data['sort_order'] ??= 0;

        $building = Building::create($data);

        return redirect()->route('app.properties.buildings.index', $property)->with('status', 'Gedung berhasil ditambahkan.');
    }

    public function edit(Request $request, int $property, int $building)
    {
        $property = $this->findProperty($request, $property);
        $building = $this->find($property, $building);
        $this->authorize('update', $building);

        return response()->view('buildings.edit', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
            'building' => $building,
        ]);
    }

    public function update(Request $request, int $property, int $building)
    {
        $property = $this->findProperty($request, $property);
        $building = $this->find($property, $building);
        $this->authorize('update', $building);

        $data = $request->validate($this->rules($property, $building->id), $this->messages());

        $building->update($data);

        return redirect()->route('app.properties.buildings.index', $property)->with('status', 'Perubahan gedung berhasil disimpan.');
    }

    public function destroy(Request $request, int $property, int $building)
    {
        $property = $this->findProperty($request, $property);
        $building = $this->find($property, $building);
        $this->authorize('delete', $building);

        $building->delete();

        return redirect()->route('app.properties.buildings.index', $property)->with('status', 'Gedung berhasil dihapus.');
    }

    public function restore(Request $request, int $property, int $building)
    {
        $property = $this->findProperty($request, $property);
        $building = $this->find($property, $building, true);
        $this->authorize('restore', $building);

        if ($building->trashed()) {
            $conflict = Building::where('property_id', $property->id)
                ->where('name', $building->name)
                ->whereNull('deleted_at')
                ->exists();

            abort_if($conflict, 422, 'Gedung "'.$building->name.'" tidak dapat dipulihkan karena nama tersebut sudah digunakan oleh data aktif.');

            $building->restore();
        }

        return redirect()->route('app.properties.buildings.index', $property)->with('status', 'Gedung berhasil dipulihkan.');
    }
}
