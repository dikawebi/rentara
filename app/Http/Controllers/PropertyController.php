<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\Property;
use App\Models\PropertyAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    private function find(Request $request, int $id, bool $withTrashed = false): Property
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;

        return Property::where('workspace_id', $workspaceId)
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
                Rule::unique('properties', 'name')
                    ->where(fn ($query) => $query->where('workspace_id', $workspaceId))
                    ->ignore($ignoreId),
            ],
            'property_type' => ['required', Rule::enum(PropertyType::class)],
            'status' => ['required', Rule::enum(PropertyStatus::class)],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.unique' => 'Nama properti sudah digunakan di ruang kerja ini (termasuk data yang telah dihapus).',
        ];
    }

    public function index(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('viewAny', [Property::class, $workspace]);

        $role = $workspace->members()
            ->where('user_id', $request->user()->id)
            ->where('status', UserStatus::Active->value)
            ->first()?->role;

        $properties = Property::where('workspace_id', $workspace->id)
            ->when($role !== WorkspaceMemberRole::Owner, function ($query) use ($workspace, $request) {
                $assignedIds = PropertyAssignment::where('workspace_id', $workspace->id)
                    ->where('user_id', $request->user()->id)
                    ->pluck('property_id');

                $query->whereIn('id', $assignedIds);
            })
            ->with('creator')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return response()->view('properties.index', ['workspace' => $workspace, 'properties' => $properties]);
    }

    public function create(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('create', [Property::class, $workspace]);

        return response()->view('properties.create', ['workspace' => $workspace]);
    }

    public function store(Request $request)
    {
        $workspace = $request->attributes->get('currentWorkspace');
        $this->authorize('create', [Property::class, $workspace]);

        $data = $request->validate($this->rules($workspace->id), $this->messages());
        $data['workspace_id'] = $workspace->id;
        $data['created_by'] = $request->user()->id;

        $property = DB::transaction(function () use ($workspace, $data, $request) {
            $property = Property::create($data);

            $role = $workspace->members()
                ->where('user_id', $request->user()->id)
                ->where('status', UserStatus::Active->value)
                ->first()?->role;

            if ($role === WorkspaceMemberRole::Manager) {
                PropertyAssignment::create([
                    'workspace_id' => $workspace->id,
                    'property_id' => $property->id,
                    'user_id' => $request->user()->id,
                    'created_by' => $request->user()->id,
                ]);
            }

            return $property;
        });

        return redirect()->route('app.properties.show', $property)->with('status', 'Properti berhasil ditambahkan.');
    }

    public function show(Request $request, int $property)
    {
        $property = $this->find($request, $property);
        $this->authorize('view', $property);

        return response()->view('properties.show', ['workspace' => $request->attributes->get('currentWorkspace'), 'property' => $property->load('creator')->loadCount(['units', 'buildings', 'floors', 'blocks', 'assignments'])]);
    }

    public function edit(Request $request, int $property)
    {
        $property = $this->find($request, $property);
        $this->authorize('update', $property);

        return response()->view('properties.edit', ['workspace' => $request->attributes->get('currentWorkspace'), 'property' => $property]);
    }

    public function update(Request $request, int $property)
    {
        $property = $this->find($request, $property);
        $this->authorize('update', $property);

        $property->update($request->validate($this->rules($property->workspace_id, $property->id), $this->messages()));

        return redirect()->route('app.properties.show', $property)->with('status', 'Perubahan properti berhasil disimpan.');
    }

    public function destroy(Request $request, int $property)
    {
        $property = $this->find($request, $property);
        $this->authorize('delete', $property);

        $property->delete();

        return redirect()->route('app.properties.index')->with('status', 'Properti berhasil dihapus.');
    }

    public function restore(Request $request, int $property)
    {
        $property = $this->find($request, $property, true);
        $this->authorize('restore', $property);

        if ($property->trashed()) {
            $conflict = Property::where('workspace_id', $property->workspace_id)
                ->where('name', $property->name)
                ->whereNull('deleted_at')
                ->exists();

            abort_if($conflict, 422, 'Properti "'.$property->name.'" tidak dapat dipulihkan karena nama tersebut sudah digunakan oleh data aktif.');

            $property->restore();
        }

        return redirect()->route('app.properties.show', $property)->with('status', 'Properti berhasil dipulihkan.');
    }
}
