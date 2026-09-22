<?php

namespace App\Services;

use App\Enums\{ContractStatus, TenantStatus, UnitStatus};
use App\Models\{CheckIn, CheckOut, RentalContract, Tenant, Unit};
use App\Support\{AuditLogger, OccupancyLockOrder};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractLifecycleService
{
    private const TRANSITIONS = [
        'submit' => [ContractStatus::Draft],
        'activate' => [ContractStatus::Draft, ContractStatus::Pending],
        'cancel' => [ContractStatus::Draft, ContractStatus::Pending, ContractStatus::Active, ContractStatus::Expiring],
        'terminate' => [ContractStatus::Active, ContractStatus::Expiring],
    ];

    private function fail(string $key, string $message): never { throw ValidationException::withMessages([$key => $message]); }
    private function assertFrom(RentalContract $c, string $action): void { if (! in_array($c->status, self::TRANSITIONS[$action] ?? [], true)) $this->fail('status', 'Transisi status kontrak tidak valid.'); }
    private function lockedPair(RentalContract $input, bool $withTrashed = false): array
    {
        // Every occupancy transaction acquires unit, contract, then tenants.
        $unit = OccupancyLockOrder::units([$input->unit_id], $input->workspace_id)->get($input->unit_id);
        $contract = OccupancyLockOrder::contracts([$input->id], $input->workspace_id, $withTrashed)->get($input->id);
        if (! $unit || ! $contract) abort(404);
        return [$unit, $contract];
    }
    private function overlap(RentalContract $c): bool { return RentalContract::where('workspace_id', $c->workspace_id)->where('property_id', $c->property_id)->where('unit_id', $c->unit_id)->whereIn('status', [ContractStatus::Active->value, ContractStatus::Expiring->value])->whereNull('deleted_at')->where('id', '!=', $c->id)->whereDate('start_date', '<=', $c->end_date)->whereDate('end_date', '>=', $c->start_date)->exists(); }

    public function submit(RentalContract $contract): RentalContract { return $this->change($contract, ContractStatus::Pending, 'submit'); }
    public function activate(RentalContract $input): RentalContract { return DB::transaction(function () use ($input) {
        [$unit, $c] = $this->lockedPair($input); $this->assertFrom($c, 'activate');
        if (! $c->start_date || ! $c->end_date || $c->end_date->lt($c->start_date)) $this->fail('end_date', 'Tanggal berakhir harus setelah tanggal mulai.');
        $contractIds = $c->tenants()->where('tenants.status', TenantStatus::Active->value)->whereNull('tenants.deleted_at')->pluck('tenants.id')->unique()->sort()->values();
        if ($contractIds->isEmpty()) $this->fail('tenant_id', 'Kontrak harus memiliki minimal satu tenant aktif.');
        $ids = Tenant::where('unit_id', $unit->id)->where('status', TenantStatus::Active->value)->whereNull('deleted_at')->pluck('id')->merge($contractIds)->unique()->sort()->values();
        $tenants = OccupancyLockOrder::tenants($ids, $c->workspace_id);
        if ($this->overlap($c)) $this->fail('start_date', 'Periode kontrak bertumpang tindih dengan kontrak aktif lainnya.');
        foreach ($contractIds as $id) { $t = $tenants->get($id); if ($t && ($t->workspace_id !== $c->workspace_id || ($t->unit_id !== null && (int) $t->unit_id !== (int) $unit->id))) $this->fail('tenant_id', 'Tenant memiliki unit aktif yang berbeda.'); }
        if ($ids->count() > $unit->capacity) $this->fail('tenant_id', 'Jumlah tenant melebihi kapasitas unit.');
        $old = $c->status; $c->update(['status' => ContractStatus::Active]); $unit->update(['status' => UnitStatus::Occupied]); $c->tenants()->whereIn('tenants.id', $contractIds->all())->update(['unit_id' => $unit->id]);
        app(AuditLogger::class)->contractStatusChanged(auth()->user(), $c, $old); return $c;
     }); }
    public function renew(RentalContract $input, array $data): RentalContract
    {
        return DB::transaction(function () use ($input, $data) {
            $unit = OccupancyLockOrder::units([$input->unit_id], $input->workspace_id)->get($input->unit_id);
            if (! $unit) abort(404);

            $contractIds = RentalContract::where('workspace_id', $input->workspace_id)
                ->where('unit_id', $unit->id)
                ->whereIn('status', [ContractStatus::Active->value, ContractStatus::Expiring->value])
                ->pluck('id')->push($input->id)->unique()->sort()->values();
            $contracts = OccupancyLockOrder::contracts($contractIds, $input->workspace_id);
            $c = $contracts->get($input->id);
            if (! $c) abort(404);
            if (! in_array($c->status, [ContractStatus::Active, ContractStatus::Expiring], true)) $this->fail('status', 'Hanya kontrak aktif yang dapat diperpanjang.');
            if ((int) $c->property_id !== (int) $unit->property_id || (int) $c->workspace_id !== (int) $unit->workspace_id) $this->fail('property_id', 'Konsistensi workspace dan properti kontrak tidak valid.');
            if ($data['end_date'] < $data['start_date']) $this->fail('end_date', 'Tanggal berakhir harus setelah tanggal mulai.');

            $contractTenantIds = $c->tenants()->pluck('tenants.id')->unique()->sort()->values();
            $currentTenantIds = Tenant::where('workspace_id', $c->workspace_id)->where('unit_id', $unit->id)
                ->where('status', TenantStatus::Active->value)->whereNull('deleted_at')->pluck('id');
            $tenantIds = $contractTenantIds->merge($currentTenantIds)->unique()->sort()->values();
            $tenants = OccupancyLockOrder::tenants($tenantIds, $c->workspace_id);
            foreach ($contractTenantIds as $id) {
                $tenant = $tenants->get($id);
                if (! $tenant || $tenant->workspace_id !== $c->workspace_id || $tenant->status !== TenantStatus::Active || ($tenant->unit_id !== null && (int) $tenant->unit_id !== (int) $unit->id)) {
                    $this->fail('tenant_id', 'Tenant kontrak harus aktif dan berada pada unit yang sama.');
                }
            }
            if ($tenantIds->count() > $unit->capacity) $this->fail('tenant_id', 'Jumlah tenant melebihi kapasitas unit.');
            if (RentalContract::where('workspace_id', $c->workspace_id)->where('unit_id', $unit->id)
                ->whereIn('status', [ContractStatus::Active->value, ContractStatus::Expiring->value])->whereNull('deleted_at')
                ->where('id', '!=', $c->id)
                ->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists()) {
                $this->fail('start_date', 'Periode kontrak bertumpang tindih dengan kontrak aktif lainnya.');
            }

            $replacement = RentalContract::create(array_merge($data, [
                'workspace_id' => $c->workspace_id, 'property_id' => $c->property_id,
                'unit_id' => $c->unit_id, 'status' => ContractStatus::Draft,
            ]));
            $replacement->tenants()->attach($contractTenantIds->all(), ['workspace_id' => $c->workspace_id]);
            app(AuditLogger::class)->contractEvent(auth()->user(), $c, AuditLogger::CONTRACT_RENEWED, $replacement->id, 'contract_id');
            return $replacement;
        });
    }
    public function cancel(RentalContract $input): RentalContract { return DB::transaction(function () use ($input) { [$unit, $c] = $this->lockedPair($input); $this->assertFrom($c, 'cancel'); $old = $c->status; $c->update(['status' => ContractStatus::Cancelled]); if (in_array($old, [ContractStatus::Active, ContractStatus::Expiring], true)) $this->release($c, $unit); app(AuditLogger::class)->contractStatusChanged(auth()->user(), $c, $old); return $c; }); }
     public function terminate(RentalContract $input, string $reason): RentalContract { $reason = trim($reason); if ($reason === '' || mb_strlen($reason) > 1000) $this->fail('reason', 'Alasan penghentian wajib diisi dan maksimal 1000 karakter.'); return DB::transaction(function () use ($input, $reason) { [$unit, $c] = $this->lockedPair($input); $this->assertFrom($c, 'terminate'); $old = $c->status; $c->update(['status' => ContractStatus::Terminated, 'termination_reason' => $reason]); $this->release($c, $unit); app(AuditLogger::class)->contractTerminated(auth()->user(), $c, $old); return $c; }); }
    private function change(RentalContract $input, ContractStatus $to, string $action): RentalContract { return DB::transaction(function () use ($input, $to, $action) { [, $c] = $this->lockedPair($input); $this->assertFrom($c, $action); $old = $c->status; $c->update(['status' => $to]); app(AuditLogger::class)->contractStatusChanged(auth()->user(), $c, $old); return $c; }); }
     public function restore(RentalContract $input): RentalContract { return DB::transaction(function () use ($input) {
         $unit = OccupancyLockOrder::units([$input->unit_id], $input->workspace_id)->get($input->unit_id); if (! $unit) abort(404);
         $activeIds = RentalContract::where('workspace_id', $input->workspace_id)->where('unit_id', $unit->id)->whereIn('status', [ContractStatus::Active->value, ContractStatus::Expiring->value])->whereNull('deleted_at')->pluck('id');
         $contracts = OccupancyLockOrder::contracts($activeIds->push($input->id), $input->workspace_id, true); $c = $contracts->get($input->id); if (! $c) abort(404);
         if (! in_array($c->status, [ContractStatus::Draft, ContractStatus::Cancelled], true)) $this->fail('status', 'Hanya kontrak draft atau dibatalkan yang dapat dipulihkan.');
         if ((int) $c->workspace_id !== (int) $unit->workspace_id || (int) $c->property_id !== (int) $unit->property_id || (int) $c->unit_id !== (int) $unit->id) $this->fail('unit_id', 'Konsistensi workspace, properti, dan unit kontrak tidak valid.');
         if ($this->overlap($c)) $this->fail('start_date', 'Periode kontrak bertumpang tindih dengan kontrak aktif lainnya.');
         $targetIds = DB::table('contract_tenant')->where('rental_contract_id', $c->id)->pluck('tenant_id'); $occupiedIds = DB::table('contract_tenant')->whereIn('rental_contract_id', $activeIds)->pluck('tenant_id');
         $tenantIds = $targetIds->merge($occupiedIds)->unique()->sort()->values(); $tenants = OccupancyLockOrder::tenants($tenantIds, $c->workspace_id);
         foreach ($targetIds->unique() as $id) { $tenant = $tenants->get($id); if (! $tenant || $tenant->trashed() || (int) $tenant->workspace_id !== (int) $c->workspace_id || $tenant->status !== TenantStatus::Active || ($tenant->unit_id !== null && (int) $tenant->unit_id !== (int) $unit->id)) $this->fail('tenant_id', 'Semua tenant kontrak harus aktif, tidak dihapus, dan konsisten dengan unit kontrak.'); }
         foreach ($occupiedIds->unique() as $id) { $tenant = $tenants->get($id); if (! $tenant || $tenant->trashed() || $tenant->status !== TenantStatus::Active || ($tenant->unit_id !== null && (int) $tenant->unit_id !== (int) $unit->id)) $this->fail('tenant_id', 'Data okupansi tenant pada unit tidak konsisten.'); }
         if ($tenantIds->count() > $unit->capacity) $this->fail('tenant_id', 'Jumlah tenant aktif pada unit melebihi kapasitas.');
         $old = $c->status; $c->restore(); app(AuditLogger::class)->contractRestored(auth()->user(), $c, $old); return $c;
     }); }
    private function release(RentalContract $c, Unit $u): void { $tenantIds = $c->tenants()->pluck('tenants.id')->sort()->values(); $tenants = OccupancyLockOrder::tenants($tenantIds, $c->workspace_id); $other = RentalContract::where('unit_id', $u->id)->whereIn('status', [ContractStatus::Active->value, ContractStatus::Expiring->value])->whereNull('deleted_at')->where('id', '!=', $c->id)->exists(); if (! $other) $u->update(['status' => UnitStatus::Available]); foreach ($tenants as $tenant) { if ((int) $tenant->unit_id !== (int) $u->id) continue; $current = RentalContract::where('workspace_id', $c->workspace_id)->whereHas('tenants', fn ($q) => $q->whereKey($tenant->id))->whereIn('status', [ContractStatus::Active->value, ContractStatus::Expiring->value])->whereNull('deleted_at')->where('id', '!=', $c->id)->exists(); if (! $current) $tenant->update(['unit_id' => null]); } }
    public function checkIn(RentalContract $input, array $data): CheckIn { return DB::transaction(function () use ($input, $data) { [, $c] = $this->lockedPair($input); if (! in_array($c->status, [ContractStatus::Active, ContractStatus::Expiring], true)) $this->fail('status', 'Check-in hanya untuk kontrak aktif.'); if ((int) ($data['deposit_received'] ?? 0) > (int) $c->deposit_amount) $this->fail('deposit_received', 'Deposit diterima melebihi nilai deposit.'); if ($c->checkIn()->exists()) return $c->checkIn; $in = CheckIn::create(['rental_contract_id' => $c->id, 'checked_in_at' => $data['checked_in_at'] ?? now(), 'deposit_received' => $data['deposit_received'] ?? 0, 'notes' => $data['notes'] ?? null]); app(AuditLogger::class)->contractEvent(auth()->user(), $c, AuditLogger::CONTRACT_CHECKED_IN, $in->id, 'check_in_id'); return $in; }); }
    public function checkOut(RentalContract $input, array $data): CheckOut { return DB::transaction(function () use ($input, $data) { [$unit, $c] = $this->lockedPair($input); if (! in_array($c->status, [ContractStatus::Active, ContractStatus::Expiring], true)) $this->fail('status', 'Check-out hanya untuk kontrak aktif.'); $in = $c->checkIn()->first(); if (! $in) $this->fail('checked_in_at', 'Check-in wajib dicatat sebelum check-out.'); if ($c->checkOut()->exists()) return $c->checkOut; $d = (int) ($data['deposit_deduction'] ?? 0); $r = (int) ($data['deposit_returned'] ?? 0); $received = (int) $in->deposit_received; if ($d < 0 || $r < 0 || $d > $received || $r > $received || $d + $r !== $received) $this->fail('deposit_returned', 'Pengembalian dan potongan deposit harus tepat sama dengan deposit yang diterima.'); $out = CheckOut::create(['rental_contract_id' => $c->id, 'checked_out_at' => $data['checked_out_at'] ?? now(), 'unpaid_amount' => $data['unpaid_amount'] ?? 0, 'damage_amount' => $data['damage_amount'] ?? 0, 'deposit_returned' => $r, 'deposit_deduction' => $d, 'reason' => $data['reason'] ?? null, 'notes' => $data['notes'] ?? null]); $old = $c->status; $c->update(['status' => ContractStatus::Completed]); $this->release($c, $unit); $logger = app(AuditLogger::class); $logger->contractStatusChanged(auth()->user(), $c, $old); if ($d + $r > 0) $logger->contractEvent(auth()->user(), $c, AuditLogger::CONTRACT_DEPOSIT_SETTLED, $out->id, 'check_out_id'); return $out; }); }
}
