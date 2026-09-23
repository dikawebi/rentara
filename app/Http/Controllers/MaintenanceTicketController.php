<?php

namespace App\Http\Controllers;

use App\Enums\{MaintenanceTicketChargeTo, MaintenanceTicketPriority, MaintenanceTicketStatus, UserStatus, WorkspaceMemberRole};
use App\Models\{MaintenanceTicket, Property, PropertyAssignment, Tenant, Unit};
use App\Services\MaintenanceTicketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceTicketController extends Controller
{
    private function workspace(Request $request)
    {
        return $request->attributes->get('currentWorkspace');
    }

    private function find(Request $request, int $id): MaintenanceTicket
    {
        return MaintenanceTicket::where('workspace_id', $this->workspace($request)->id)
            ->with(['property', 'unit', 'tenant', 'assignee', 'submitter', 'history.actor', 'media'])
            ->findOrFail($id);
    }

    private function rules(): array
    {
        return [
            'property_id' => ['required', 'integer'], 'unit_id' => ['required', 'integer'], 'tenant_id' => ['nullable', 'integer'],
            'priority' => ['required', Rule::enum(MaintenanceTicketPriority::class)], 'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'], 'estimated_cost' => ['nullable', 'integer', 'min:0'],
            'actual_cost' => ['nullable', 'integer', 'min:0'], 'charged_to' => ['nullable', Rule::enum(MaintenanceTicketChargeTo::class)],
        ];
    }

    private function formData(Request $request): array
    {
        $w = $this->workspace($request);
        $user = $request->user();
        $role = $w->members()->where('user_id', $user->id)->where('status', UserStatus::Active->value)->first()?->role;
        $properties = Property::where('workspace_id', $w->id)->where('status', 'active')->when($role !== WorkspaceMemberRole::Owner, fn ($q) => $q->whereHas('assignments', fn ($a) => $a->where('user_id', $user->id)))->orderBy('name')->get();
        $units = Unit::where('workspace_id', $w->id)->whereIn('property_id', $properties->pluck('id'))->with('property')->orderBy('unit_number')->get();
        $tenants = Tenant::where('workspace_id', $w->id)->where('status', 'active')->whereIn('unit_id', $units->pluck('id'))->with('unit')->orderBy('name')->get();
        return compact('properties', 'units', 'tenants');
    }

    public function index(Request $request)
    {
        $w = $this->workspace($request);
        $this->authorize('viewAny', [MaintenanceTicket::class, $w]);
        $tickets = MaintenanceTicket::where('workspace_id', $w->id)
            ->whereHas('property', fn ($q) => $q->where('status', 'active'))
            ->whereHas('unit')
            ->when($request->user()->can('viewAll', $w), fn ($q) => $q, fn ($q) => $q->whereHas('property', fn ($p) => $p->whereHas('assignments', fn ($a) => $a->where('user_id', $request->user()->id))))
            ->with(['property', 'unit', 'assignee'])->latest()->paginate(15);
        return view('maintenance-tickets.index', compact('tickets'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', [MaintenanceTicket::class, $this->workspace($request)]);
        return view('maintenance-tickets.create', $this->formData($request));
    }

    public function store(Request $request, MaintenanceTicketService $service)
    {
        $w = $this->workspace($request);
        $this->authorize('create', [MaintenanceTicket::class, $w]);
        $data = $request->validate($this->rules());
        $data['workspace_id'] = $w->id;
        $ticket = $service->create($data, $request->user());
        return redirect()->route('app.maintenance-tickets.show', $ticket)->with('status', 'Tiket pemeliharaan berhasil dibuat.');
    }

    public function show(Request $request, int $ticket)
    {
        $t = $this->find($request, $ticket);
        $this->authorize('view', $t);
        $assignmentCandidates = PropertyAssignment::where('workspace_id', $t->workspace_id)->where('property_id', $t->property_id)->with('user')->get()->pluck('user')->filter(fn ($user) => $user?->status === UserStatus::Active && in_array($t->workspace->members()->where('user_id', $user->id)->first()?->role, [WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true))->unique('id');
        return view('maintenance-tickets.show', compact('t', 'assignmentCandidates'));
    }

    public function edit(Request $request, int $ticket)
    {
        $t = $this->find($request, $ticket);
        $this->authorize('update', $t);
        return view('maintenance-tickets.edit', ['t' => $t] + $this->formData($request));
    }

    public function update(Request $request, int $ticket, MaintenanceTicketService $service)
    {
        $t = $this->find($request, $ticket);
        $this->authorize('update', $t);
        $role = $this->workspace($request)->members()->where('user_id', $request->user()->id)->first()?->role;
        $data = $role === WorkspaceMemberRole::Staff
            ? $request->validate(['priority' => ['required', Rule::enum(MaintenanceTicketPriority::class)], 'title' => ['required', 'string', 'max:160'], 'description' => ['required', 'string', 'max:10000']])
            : $request->validate($this->rules());
        $service->update($t, $data, $request->user());
        return back()->with('status', 'Tiket berhasil diperbarui.');
    }

    public function transition(Request $request, int $ticket, MaintenanceTicketService $service)
    {
        $t = $this->find($request, $ticket); $this->authorize('transition', $t);
        $data = $request->validate(['status' => ['required', Rule::enum(MaintenanceTicketStatus::class)], 'reason' => ['nullable', 'string', 'max:2000']]);
        $service->transition($t, MaintenanceTicketStatus::from($data['status']), $data['reason'] ?? null, $request->user());
        return back()->with('status', 'Status tiket berhasil diperbarui.');
    }

    public function assign(Request $request, int $ticket, MaintenanceTicketService $service)
    {
        $t = $this->find($request, $ticket); $this->authorize('assign', $t);
        $data = $request->validate(['assigned_to' => ['required', 'integer']]);
        $service->assign($t, (int) $data['assigned_to'], $request->user());
        return back()->with('status', 'Tiket berhasil ditugaskan.');
    }

    public function destroy(Request $request, int $ticket, MaintenanceTicketService $service)
    {
        $t = $this->find($request, $ticket); $this->authorize('delete', $t); $service->delete($t, $request->user());
        return back()->with('status', 'Tiket berhasil dihapus.');
    }

    public function restore(Request $request, int $ticket)
    {
        $t = MaintenanceTicket::onlyTrashed()->where('workspace_id', $this->workspace($request)->id)->with(['property','unit'])->findOrFail($ticket);
        $this->authorize('restore', $t);
        $service = app(MaintenanceTicketService::class);
        abort_unless($service->parentIsRestorable($t), 422, 'Parent tiket tidak lagi valid.');
        $service->restore($t, $request->user());
        return back()->with('status', 'Tiket berhasil dipulihkan.');
    }
}
