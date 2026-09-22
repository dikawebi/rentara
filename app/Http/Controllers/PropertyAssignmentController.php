<?php

namespace App\Http\Controllers;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\Property;
use App\Models\PropertyAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyAssignmentController extends Controller
{
    private function findProperty(Request $request, int $propertyId): Property
    {
        $workspaceId = $request->attributes->get('currentWorkspace')->id;

        return Property::where('workspace_id', $workspaceId)->findOrFail($propertyId);
    }

    private function find(Property $property, int $id): PropertyAssignment
    {
        return PropertyAssignment::where('workspace_id', $property->workspace_id)
            ->where('property_id', $property->id)
            ->findOrFail($id);
    }

    public function index(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('viewAny', [PropertyAssignment::class, $property]);

        $assignments = PropertyAssignment::where('property_id', $property->id)
            ->with(['user', 'creator'])
            ->orderBy('id')
            ->paginate(15)->withQueryString();

        $assignedUserIds = PropertyAssignment::where('property_id', $property->id)->pluck('user_id');

        $candidates = User::where('status', UserStatus::Active->value)
            ->where(function ($query) {
                $query->whereNull('platform_role')->orWhere('platform_role', '!=', PlatformRole::SuperAdmin->value);
            })
            ->whereIn('id', function ($query) use ($property) {
                $query->select('user_id')->from('workspace_members')
                    ->where('workspace_id', $property->workspace_id)
                    ->whereIn('role', [WorkspaceMemberRole::Manager->value, WorkspaceMemberRole::Staff->value])
                    ->where('status', UserStatus::Active->value);
            })
            ->whereNotIn('id', $assignedUserIds)
            ->orderBy('name')
            ->get();

        return response()->view('assignments.index', [
            'workspace' => $request->attributes->get('currentWorkspace'),
            'property' => $property,
            'assignments' => $assignments,
            'candidates' => $candidates,
        ]);
    }

    public function store(Request $request, int $property)
    {
        $property = $this->findProperty($request, $property);
        $this->authorize('create', [PropertyAssignment::class, $property]);

        $data = $request->validate([
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id'),
                Rule::unique('property_assignments', 'user_id')
                    ->where(fn ($query) => $query->where('property_id', $property->id)),
                function (string $attribute, mixed $value, \Closure $fail) use ($property) {
                    $user = User::find($value);

                    if (! $user || $user->status !== UserStatus::Active || $user->platform_role === PlatformRole::SuperAdmin) {
                        $fail('Pengguna yang dipilih tidak aktif atau tidak tersedia.');

                        return;
                    }

                    $membership = $user->workspaceMemberships()
                        ->where('workspace_id', $property->workspace_id)
                        ->where('status', UserStatus::Active->value)
                        ->first();

                    if (! $membership || ! in_array($membership->role, [WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true)) {
                        $fail('Pengguna yang dipilih bukan manajer atau staf aktif pada ruang kerja ini.');
                    }
                },
            ],
        ], [
            'user_id.unique' => 'Pengguna tersebut sudah ditugaskan pada properti ini.',
        ]);

        PropertyAssignment::create([
            'workspace_id' => $property->workspace_id,
            'property_id' => $property->id,
            'user_id' => $data['user_id'],
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('app.properties.assignments.index', $property)->with('status', 'Penugasan berhasil ditambahkan.');
    }

    public function destroy(Request $request, int $property, int $assignment)
    {
        $property = $this->findProperty($request, $property);
        $assignment = $this->find($property, $assignment);
        $this->authorize('delete', $assignment);

        $assignment->delete();

        return redirect()->route('app.properties.assignments.index', $property)->with('status', 'Penugasan berhasil dihapus.');
    }
}
