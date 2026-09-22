<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\UnitType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitTypeController extends Controller
{
    private function find(Request $request, int $id, bool $withTrashed = false): UnitType
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;

        return UnitType::where('workspace_id', $workspaceId)
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(int $workspaceId, ?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('unit_types', 'name')
                    ->where(fn ($query) => $query->where('workspace_id', $workspaceId))
                    ->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.unique' => 'Nama tipe unit sudah digunakan di ruang kerja ini (termasuk data yang telah dihapus).',
        ];
    }

    public function index(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('viewAny', [UnitType::class, $workspace]);

        $unitTypes = UnitType::where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return response()->view('unit-types.index', ['workspace' => $workspace, 'unitTypes' => $unitTypes]);
    }

    public function create(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('create', [UnitType::class, $workspace]);

        return response()->view('unit-types.create', ['workspace' => $workspace]);
    }

    public function store(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('create', [UnitType::class, $workspace]);

        $data = $request->validate($this->rules($workspace->id), $this->messages());
        $data['workspace_id'] = $workspace->id;

        $unitType = UnitType::create($data);

        return redirect()->route('app.unit-types.index')->with('status', 'Tipe unit berhasil ditambahkan.');
    }

    public function edit(Request $request, int $unitType)
    {
        $unitType = $this->find($request, $unitType);
        $this->authorize('update', $unitType);

        return response()->view('unit-types.edit', ['workspace' => $request->attributes->get('currentWorkspace'), 'unitType' => $unitType]);
    }

    public function update(Request $request, int $unitType)
    {
        $unitType = $this->find($request, $unitType);
        $this->authorize('update', $unitType);

        $unitType->update($request->validate($this->rules($unitType->workspace_id, $unitType->id), $this->messages()));

        return redirect()->route('app.unit-types.index')->with('status', 'Perubahan tipe unit berhasil disimpan.');
    }

    public function destroy(Request $request, int $unitType)
    {
        $unitType = $this->find($request, $unitType);
        $this->authorize('delete', $unitType);

        $unitType->delete();

        return redirect()->route('app.unit-types.index')->with('status', 'Tipe unit berhasil dihapus.');
    }

    public function restore(Request $request, int $unitType)
    {
        $unitType = $this->find($request, $unitType, true);
        $this->authorize('restore', $unitType);

        if ($unitType->trashed()) {
            $conflict = UnitType::where('workspace_id', $unitType->workspace_id)
                ->where('name', $unitType->name)
                ->whereNull('deleted_at')
                ->exists();

            abort_if($conflict, 422, 'Tipe unit "'.$unitType->name.'" tidak dapat dipulihkan karena nama tersebut sudah digunakan oleh data aktif.');

            $unitType->restore();
        }

        return redirect()->route('app.unit-types.index')->with('status', 'Tipe unit berhasil dipulihkan.');
    }
}
