<?php

namespace App\Http\Controllers;

use App\Models\Floor;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FloorController extends Controller
{
    private function findProperty(Request $request, int $propertyId): Property
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;

        return Property::where('workspace_id', $workspaceId)->findOrFail($propertyId);
    }

    private function find(Property $property, int $id, bool $withTrashed = false): Floor
    {
        return Floor::where('workspace_id', $property->workspace_id)
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
                Rule::unique('floors', 'name')
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
            'name.unique' => 'Nama lantai sudah digunakan pada properti ini (termasuk data yang telah dihapus).',
        ];
    }

    public function index(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('viewAny', [Floor::class, $property]);

        $floors = Floor::where('property_id', $property->id)
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(15)->withQueryString();

        return response()->view('floors.index', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
            'floors' => $floors,
        ]);
    }

    public function create(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('create', [Floor::class, $property]);

        return response()->view('floors.create', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
        ]);
    }

    public function store(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('create', [Floor::class, $property]);

        $data = $request->validate($this->rules($property), $this->messages());
        $data['workspace_id'] = $property->workspace_id;
        $data['property_id'] = $property->id;
        $data['sort_order'] ??= 0;

        $floor = Floor::create($data);

        return redirect()->route('app.properties.floors.index', $property)->with('status', 'Lantai berhasil ditambahkan.');
    }

    public function edit(Request $request, int $property, int $floor)
    {
        $property = $this->findProperty($request, $property);
        $floor = $this->find($property, $floor);
        $this->authorize('update', $floor);

        return response()->view('floors.edit', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
            'floor' => $floor,
        ]);
    }

    public function update(Request $request, int $property, int $floor)
    {
        $property = $this->findProperty($request, $property);
        $floor = $this->find($property, $floor);
        $this->authorize('update', $floor);

        $data = $request->validate($this->rules($property, $floor->id), $this->messages());

        $floor->update($data);

        return redirect()->route('app.properties.floors.index', $property)->with('status', 'Perubahan lantai berhasil disimpan.');
    }

    public function destroy(Request $request, int $property, int $floor)
    {
        $property = $this->findProperty($request, $property);
        $floor = $this->find($property, $floor);
        $this->authorize('delete', $floor);

        $floor->delete();

        return redirect()->route('app.properties.floors.index', $property)->with('status', 'Lantai berhasil dihapus.');
    }

    public function restore(Request $request, int $property, int $floor)
    {
        $property = $this->findProperty($request, $property);
        $floor = $this->find($property, $floor, true);
        $this->authorize('restore', $floor);

        if ($floor->trashed()) {
            $conflict = Floor::where('property_id', $property->id)
                ->where('name', $floor->name)
                ->whereNull('deleted_at')
                ->exists();

            abort_if($conflict, 422, 'Lantai "'.$floor->name.'" tidak dapat dipulihkan karena nama tersebut sudah digunakan oleh data aktif.');

            $floor->restore();
        }

        return redirect()->route('app.properties.floors.index', $property)->with('status', 'Lantai berhasil dipulihkan.');
    }
}
