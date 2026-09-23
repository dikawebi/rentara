<?php

namespace App\Http\Controllers;

use App\Enums\{ContractStatus, InvoiceStatus, TenantStatus, WorkspaceMemberRole};
use App\Models\{Invoice, Property, PropertyAssignment, RentalContract, Tenant};
use App\Support\{AuditLogger, OccupancyLockOrder};
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    private const MAX_AMOUNT = PHP_INT_MAX;
    private const PAYMENT_METHODS = ['cash', 'bank_transfer', 'other'];
    private function workspace(Request $request) { return $request->attributes->get('currentWorkspace'); }
    private function find(Request $request, int $id): Invoice { return Invoice::where('workspace_id', $this->workspace($request)->id)->whereHas('property', fn ($q) => $q->where('workspace_id', $this->workspace($request)->id))->findOrFail($id); }
    private function assignedPropertyIds(Request $request) { return PropertyAssignment::where('workspace_id', $this->workspace($request)->id)->where('user_id', $request->user()->id)->pluck('property_id'); }
    private function invoiceNumberRule(): Closure { return static function (string $attribute, mixed $value, Closure $fail): void { if (! is_string($value) || preg_match(AuditLogger::INVOICE_NUMBER_PATTERN, $value) !== 1) $fail('Format nomor invoice tidak valid. Gunakan huruf, angka, titik, garis bawah, garis miring, atau tanda hubung (maksimal 100 karakter).'); }; }
    private function rules(): array { return ['contract_id' => ['required', 'integer'], 'tenant_id' => ['required', 'integer'], 'invoice_number' => ['required', 'string', 'max:100', $this->invoiceNumberRule()], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'], 'due_date' => ['required', 'date'], 'amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT]]; }

    /** Lock the complete graph before making any billing decision. */
    private function lockedGraph(int $workspaceId, int $contractId, int $tenantId, ?int $invoicePropertyId = null): array
    {
        // This is the shared occupancy order: unit -> property -> contract -> tenant.
        $candidate = RentalContract::where('workspace_id', $workspaceId)->findOrFail($contractId);
        $units = OccupancyLockOrder::units([$candidate->unit_id], $workspaceId);
        $properties = Property::where('workspace_id', $workspaceId)->whereKey($candidate->property_id)->lockForUpdate()->get()->keyBy('id');
        $contract = OccupancyLockOrder::contracts([$contractId], $workspaceId)->get($contractId);
        $unit = $units->get($contract?->unit_id);
        $property = $properties->get($contract?->property_id);
        $tenant = OccupancyLockOrder::tenants([$tenantId], $workspaceId)->get($tenantId);
        if (! $contract || ! $unit || ! $property || ! $tenant || $tenant->deleted_at !== null || $tenant->status !== TenantStatus::Active || ($tenant->unit_id !== null && (int) $tenant->unit_id !== (int) $unit->id) || ($invoicePropertyId !== null && (int) $invoicePropertyId !== (int) $contract->property_id)) {
            throw ValidationException::withMessages(['contract_id' => 'Kombinasi kontrak, properti, unit, dan tenant tidak valid.']);
        }
        return [$contract, $property, $unit, $tenant];
    }

    private function scoped(Request $request, array $data): array
    {
        $workspace = $this->workspace($request);
        [$contract, $property, $unit, $tenant] = $this->lockedGraph($workspace->id, (int) $data['contract_id'], (int) $data['tenant_id']);
        if (! in_array($contract->status, [ContractStatus::Active, ContractStatus::Expiring], true)) throw ValidationException::withMessages(['contract_id' => 'Invoice hanya dapat dibuat untuk kontrak aktif atau akan berakhir.']);
        if ((int) $contract->workspace_id !== (int) $workspace->id || (int) $property->workspace_id !== (int) $workspace->id || (int) $unit->workspace_id !== (int) $workspace->id || (int) $unit->property_id !== (int) $property->id || (int) $contract->property_id !== (int) $property->id || (int) $contract->unit_id !== (int) $unit->id || $tenant->deleted_at !== null || $tenant->status !== TenantStatus::Active || ($tenant->unit_id !== null && (int) $tenant->unit_id !== (int) $unit->id)) throw ValidationException::withMessages(['contract_id' => 'Kontrak, properti, unit, dan tenant harus berada dalam ruang kerja dan properti yang sama.']);
        $role = $workspace->members()->where('user_id', $request->user()->id)->first()?->role;
        if ($role === WorkspaceMemberRole::Manager && ! PropertyAssignment::where('workspace_id', $workspace->id)->where('property_id', $contract->property_id)->where('user_id', $request->user()->id)->exists()) abort(403);
        if (! $contract->tenants()->wherePivot('workspace_id', $workspace->id)->whereKey($tenant->id)->exists()) throw ValidationException::withMessages(['tenant_id' => 'Tenant bukan bagian dari kontrak ini.']);
        if (CarbonImmutable::parse($data['due_date'])->lt(CarbonImmutable::parse($data['period_start']))) throw ValidationException::withMessages(['due_date' => 'Jatuh tempo tidak boleh sebelum periode tagihan.']);
        if (CarbonImmutable::parse($data['period_end'])->lt(CarbonImmutable::parse($contract->start_date)) || CarbonImmutable::parse($data['period_start'])->gt(CarbonImmutable::parse($contract->end_date))) throw ValidationException::withMessages(['period_start' => 'Periode harus beririsan dengan masa kontrak.']);
        return array_merge($data, ['workspace_id' => $workspace->id, 'property_id' => $contract->property_id, 'contract_id' => $contract->id, 'tenant_id' => $tenant->id, 'currency' => $workspace->currency, 'status' => InvoiceStatus::Unpaid, 'created_by' => $request->user()->id]);
    }
    private function uniqueError(QueryException $e): bool { $code = (string) ($e->errorInfo[0] ?? $e->getCode()); return in_array($code, ['19', '23000', '23505'], true) && (str_contains($e->getMessage(), 'invoice_number') || str_contains($e->getMessage(), 'contract_id') || str_contains($e->getMessage(), 'invoices_workspace_id_invoice_number_unique') || str_contains($e->getMessage(), 'invoices_workspace_id_contract_id_period_start_period_end_unique')); }
    public function index(Request $request) { $w = $this->workspace($request); $this->authorize('viewAny', [Invoice::class, $w]); $role = $w->members()->where('user_id', $request->user()->id)->first()?->role; $invoices = Invoice::where('workspace_id', $w->id)->when($role !== WorkspaceMemberRole::Owner, fn ($q) => $q->whereIn('property_id', $this->assignedPropertyIds($request)))->with(['property', 'tenant', 'contract.unit'])->latest()->paginate(15); return view('invoices.index', compact('w', 'invoices')); }
    public function create(Request $request) { $w = $this->workspace($request); $this->authorize('create', [Invoice::class, $w]); $contracts = RentalContract::where('workspace_id', $w->id)->whereIn('status', [ContractStatus::Active->value, ContractStatus::Expiring->value])->when($w->members()->where('user_id', $request->user()->id)->first()?->role !== WorkspaceMemberRole::Owner, fn ($q) => $q->whereIn('property_id', $this->assignedPropertyIds($request)))->with(['property', 'unit', 'tenants'])->orderBy('contract_number')->get(); return view('invoices.create', compact('w', 'contracts')); }
    public function store(Request $request) { $w = $this->workspace($request); $this->authorize('create', [Invoice::class, $w]); $input = $request->validate($this->rules()); try { $invoice = DB::transaction(function () use ($request, $input) { $invoice = Invoice::create($this->scoped($request, $input)); app(AuditLogger::class)->invoiceEvent(AuditLogger::INVOICE_CREATED, $request->user(), $invoice, null, app(AuditLogger::class)->invoiceValues($invoice)); return $invoice; }); } catch (QueryException $e) { if ($this->uniqueError($e)) throw ValidationException::withMessages(['invoice_number' => 'Nomor invoice atau periode kontrak sudah digunakan.']); throw $e; } return redirect()->route('app.invoices.show', $invoice)->with('status', 'Invoice berhasil dibuat.'); }
    public function show(Request $request, int $invoice) { $model = $this->find($request, $invoice)->load(['property', 'contract.unit', 'tenant', 'creator', 'payer']); $this->authorize('view', $model); return view('invoices.show', ['w' => $this->workspace($request), 'invoice' => $model]); }
    public function edit(Request $request, int $invoice) { $model = $this->find($request, $invoice); $this->authorize('update', $model); return view('invoices.edit', ['w' => $this->workspace($request), 'invoice' => $model]); }
    public function update(Request $request, int $invoice)
    {
        $model = $this->find($request, $invoice); $this->authorize('update', $model);
         $data = $request->validate(['invoice_number' => ['required', 'string', 'max:100', $this->invoiceNumberRule()], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'], 'due_date' => ['required', 'date'], 'amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT]]); $old = ['status' => $model->status->value];
         try { DB::transaction(function () use ($request, $model, $data): void { $locked = Invoice::where('workspace_id', $model->workspace_id)->whereKey($model->id)->lockForUpdate()->firstOrFail(); [$contract, $property, $unit, $tenant] = $this->lockedGraph($locked->workspace_id, $locked->contract_id, $locked->tenant_id, $locked->property_id); if (! in_array($contract->status, [ContractStatus::Active, ContractStatus::Expiring], true)) throw ValidationException::withMessages(['contract_id' => 'Invoice hanya dapat diubah untuk kontrak aktif atau akan berakhir.']); if (! $contract->tenants()->wherePivot('workspace_id', $locked->workspace_id)->whereKey($tenant->id)->exists()) throw ValidationException::withMessages(['contract_id' => 'Kombinasi kontrak, properti, unit, dan tenant tidak valid.']); if (CarbonImmutable::parse($data['due_date'])->lt(CarbonImmutable::parse($data['period_start']))) throw ValidationException::withMessages(['due_date' => 'Jatuh tempo tidak boleh sebelum periode tagihan.']); if (CarbonImmutable::parse($data['period_end'])->lt(CarbonImmutable::parse($contract->start_date)) || CarbonImmutable::parse($data['period_start'])->gt(CarbonImmutable::parse($contract->end_date))) throw ValidationException::withMessages(['period_start' => 'Periode harus beririsan dengan masa kontrak.']); if ($locked->status !== InvoiceStatus::Unpaid) abort(422, 'Invoice lunas tidak dapat diubah.'); $old = app(AuditLogger::class)->invoiceValues($locked); $locked->update($data); app(AuditLogger::class)->invoiceEvent(AuditLogger::INVOICE_UPDATED, $request->user(), $locked, $old, app(AuditLogger::class)->invoiceValues($locked)); }); } catch (QueryException $e) { if ($this->uniqueError($e)) throw ValidationException::withMessages(['invoice_number' => 'Nomor invoice atau periode kontrak sudah digunakan.']); throw $e; }
        return redirect()->route('app.invoices.show', $model)->with('status', 'Perubahan invoice berhasil disimpan.');
    }
    public function pay(Request $request, int $invoice)
    {
        $model = $this->find($request, $invoice); $this->authorize('pay', $model); $data = $request->validate(['payment_date' => ['required', 'date_format:Y-m-d'], 'payment_method' => ['required', 'string', Rule::in(self::PAYMENT_METHODS)], 'payment_reference' => ['nullable', 'string', 'max:255'], 'payment_notes' => ['nullable', 'string', 'max:2000']]); $paymentDate = $data['payment_date'];
        DB::transaction(function () use ($model, $data, $request, $paymentDate): void { $locked = Invoice::where('workspace_id', $model->workspace_id)->whereKey($model->id)->lockForUpdate()->firstOrFail(); $payment = ['payment_date' => $paymentDate, 'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'] ?? null, 'payment_notes' => $data['payment_notes'] ?? null]; if ($locked->status === InvoiceStatus::Paid) { $stored = ['payment_date' => $locked->paid_date?->toDateString(), 'payment_method' => $locked->payment_method, 'payment_reference' => $locked->payment_reference, 'payment_notes' => $locked->payment_notes]; if ($stored !== $payment) throw ValidationException::withMessages(['invoice' => 'Pembayaran invoice sudah dicatat dengan data berbeda.']); return; } $locked->update(['status' => InvoiceStatus::Paid, 'paid_at' => now(), 'paid_by' => $request->user()->id, 'payment_method' => $payment['payment_method'], 'payment_reference' => $payment['payment_reference'], 'payment_notes' => $payment['payment_notes']]); DB::table('invoices')->where('id', $locked->id)->update(['paid_date' => $paymentDate]); app(AuditLogger::class)->invoiceEvent(AuditLogger::INVOICE_PAYMENT_RECORDED, $request->user(), $locked, ['status' => InvoiceStatus::Unpaid->value], ['invoice_id' => (int) $locked->id, 'status' => InvoiceStatus::Paid->value, 'payment_date' => $payment['payment_date'], 'payment_method' => $payment['payment_method']]); });
        return redirect()->route('app.invoices.show', $model)->with('status', 'Pembayaran dicatat.');
    }
}
