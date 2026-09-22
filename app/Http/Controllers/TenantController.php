<?php

namespace App\Http\Controllers;

use App\Enums\TenantStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\PropertyAssignment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\RentalContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Support\OccupancyLockOrder;

class TenantController extends Controller
{
    private function workspace(Request $request) { return $request->attributes->get('currentWorkspace'); }

    private function find(Request $request, int $id, bool $withTrashed = false): Tenant
    {
        return Tenant::where('workspace_id', $this->workspace($request)->id)
            ->when($withTrashed, fn (Builder $q) => $q->withTrashed())->findOrFail($id);
    }

    private function unit(Request $request, mixed $id): ?Unit
    {
        if ($id === null || $id === '') return null;
        $unit = Unit::where('workspace_id', $this->workspace($request)->id)
            ->whereNull('deleted_at')->find((int) $id);
        if (! $unit) {
            throw ValidationException::withMessages(['unit_id' => 'Unit tidak ditemukan, sudah dihapus, atau bukan bagian dari ruang kerja ini.']);
        }
        return $unit;
    }

    private function normalizePhones(Request $request): void
    {
        foreach (['phone', 'emergency_contact_phone'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => preg_replace('/[\s().-]+/', '', trim((string) $request->input($field)))]);
            }
        }
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{4,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'unit_id' => ['nullable', 'integer'],
            'identity_number' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{4,15}$/'],
            'status' => ['required', Rule::enum(TenantStatus::class)],
        ];
    }

    private function messages(): array
    {
        return [
            'phone.regex' => 'Nomor telepon harus terdiri dari 4–15 digit dan boleh diawali tanda +.',
            'emergency_contact_phone.regex' => 'Nomor telepon kontak darurat harus terdiri dari 4–15 digit dan boleh diawali tanda +.',
        ];
    }

    private function units(Request $request)
    {
        $workspace = $this->workspace($request);
        $role = $workspace->members()->where('user_id', $request->user()->id)->first()?->role;
        return Unit::where('workspace_id', $workspace->id)->whereNull('deleted_at')
            ->when($role !== WorkspaceMemberRole::Owner, function (Builder $q) use ($workspace, $request) {
                $q->whereIn('property_id', PropertyAssignment::where('workspace_id', $workspace->id)
                    ->where('user_id', $request->user()->id)->pluck('property_id'));
            })->with('property')->orderBy('property_id')->orderBy('unit_number')->get();
    }

    private function authorizeUnit(Request $request, ?Tenant $tenant, ?Unit $unit): void
    {
        $workspace = $this->workspace($request);
        $args = $tenant
            ? ['updateForUnits', [Tenant::class, $tenant, $tenant->unit, $unit]]
            : ['createForUnit', [Tenant::class, $workspace, $unit]];
        $this->authorize($args[0], $args[1]);
    }

    private function saveWithCapacity(Request $request, Tenant $tenant, array $data, ?Unit $unit): void
    {
        DB::transaction(function () use ($request, $tenant, $data, $unit) {
            $contractIds = $tenant->exists
                ? RentalContract::whereHas('tenants', fn($q) => $q->whereKey($tenant->id))->whereIn('status', ['active','expiring'])->orderBy('id')->pluck('id')
                : collect();
            if ($unit) {
                $unit = Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
                $count = Tenant::where('unit_id', $unit->id)->where('status', TenantStatus::Active->value)
                    ->whereNull('deleted_at')->when($tenant->exists, fn ($q) => $q->where('id', '!=', $tenant->id))->count();
                if ($data['status'] === TenantStatus::Active->value && $count >= $unit->capacity) {
                    throw ValidationException::withMessages(['unit_id' => 'Kapasitas unit sudah penuh.']);
                }
            }
            OccupancyLockOrder::contracts($contractIds, $tenant->workspace_id ?: $this->workspace($request)->id);
            $lockedTenant = $tenant->exists ? Tenant::withTrashed()->whereKey($tenant->id)->lockForUpdate()->firstOrFail() : $tenant;
            $oldUnitId = $lockedTenant->unit_id;
            if ($tenant->exists && ($oldUnitId != ($data['unit_id'] ?? null) || (($data['status'] ?? $lockedTenant->status->value) !== $lockedTenant->status->value))) {
                if ($contractIds->isNotEmpty()) throw ValidationException::withMessages(['unit_id' => 'Tenant terikat kontrak aktif dan hanya dapat diubah melalui siklus kontrak.']);
            }
            if ($tenant->trashed()) {
                $lockedTenant->restore();
            }
            $lockedTenant->fill($data)->save();
        });
    }

    public function index(Request $request)
    {
        $workspace = $this->workspace($request);
        $this->authorize('viewAny', [Tenant::class, $workspace]);
        $role = $workspace->members()->where('user_id', $request->user()->id)->first()?->role;
        $propertyIds = PropertyAssignment::where('workspace_id', $workspace->id)->where('user_id', $request->user()->id)->pluck('property_id');
        $search = trim((string) $request->query('q'));
        $tenants = Tenant::where('workspace_id', $workspace->id)->with('unit')
             ->when($role !== WorkspaceMemberRole::Owner, fn ($q) => $q->whereHas('unit', fn (Builder $unitQuery) => $unitQuery->whereIn('property_id', $propertyIds)))
            ->when($search !== '', fn (Builder $q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")->orWhereHas('unit', fn ($u) => $u->where('unit_number', 'like', "%{$search}%"));
            }))->orderBy('name')->paginate(15)->withQueryString();
        return view('tenants.index', compact('workspace', 'tenants', 'search'));
    }

    public function create(Request $request)
    {
        $workspace = $this->workspace($request);
        $this->authorize('viewAny', [Tenant::class, $workspace]);
        return view('tenants.create', ['workspace' => $workspace, 'units' => $this->units($request)]);
    }

    public function store(Request $request)
    {
        $workspace = $this->workspace($request);
        $this->normalizePhones($request);
        $data = $request->validate($this->rules(), $this->messages());
        $unit = $this->unit($request, $data['unit_id'] ?? null);
        $this->authorizeUnit($request, null, $unit);
        $data['workspace_id'] = $workspace->id;
        $tenant = new Tenant;
        $this->saveWithCapacity($request, $tenant, $data, $unit);
        return redirect()->route('app.tenants.show', $tenant)->with('status', 'Tenant berhasil ditambahkan.');
    }

    public function show(Request $request, int $tenant)
    {
        $tenant = $this->find($request, $tenant)->load('unit');
        $this->authorize('view', $tenant);
        return view('tenants.show', ['workspace' => $this->workspace($request), 'tenant' => $tenant]);
    }

    public function edit(Request $request, int $tenant)
    {
        $tenant = $this->find($request, $tenant)->load('unit');
        $this->authorize('update', $tenant);
        return view('tenants.edit', ['workspace' => $this->workspace($request), 'tenant' => $tenant, 'units' => $this->units($request)]);
    }

    public function update(Request $request, int $tenant)
    {
        $tenant = $this->find($request, $tenant)->load('unit');
        $this->normalizePhones($request);
        $data = $request->validate($this->rules(), $this->messages());
        foreach (['identity_number', 'date_of_birth', 'gender', 'occupation', 'emergency_contact_name', 'emergency_contact_phone'] as $field) {
            if (($data[$field] ?? null) === null || $data[$field] === '') unset($data[$field]);
        }
        $unit = $this->unit($request, $data['unit_id'] ?? null);
        $this->authorizeUnit($request, $tenant, $unit);
        $this->saveWithCapacity($request, $tenant, $data, $unit);
        return redirect()->route('app.tenants.show', $tenant)->with('status', 'Perubahan tenant berhasil disimpan.');
    }

    public function destroy(Request $request, int $tenant)
    {
        $tenant = $this->find($request, $tenant);
        $this->authorize('delete', $tenant);
        DB::transaction(function () use ($tenant) { $unitIds = [$tenant->unit_id]; OccupancyLockOrder::units($unitIds, $tenant->workspace_id); $contractIds = RentalContract::whereHas('tenants', fn($q) => $q->whereKey($tenant->id))->whereIn('status',['active','expiring'])->orderBy('id')->pluck('id'); OccupancyLockOrder::contracts($contractIds, $tenant->workspace_id); $locked = OccupancyLockOrder::tenants([$tenant->id], $tenant->workspace_id)->get($tenant->id); if ($contractIds->isNotEmpty()) throw ValidationException::withMessages(['tenant'=>'Tenant terikat kontrak aktif dan tidak dapat dihapus.']); $locked->delete(); });
        return redirect()->route('app.tenants.index')->with('status', 'Tenant berhasil dihapus.');
    }

    public function restore(Request $request, int $tenant)
    {
        $tenant = $this->find($request, $tenant, true);
        $unit = $this->unit($request, $tenant->unit_id);
        $this->authorizeUnit($request, $tenant, $unit);
        if ($tenant->trashed()) {
            $this->saveWithCapacity($request, $tenant, ['status' => $tenant->status->value], $unit);
        }
        return redirect()->route('app.tenants.show', $tenant)->with('status', 'Tenant berhasil dipulihkan.');
    }
}
