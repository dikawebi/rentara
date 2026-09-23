<?php

namespace App\Services;

use App\Enums\{MaintenanceTicketChargeTo, MaintenanceTicketStatus, PlatformRole, PropertyStatus, UserStatus, WorkspaceMemberRole};
use App\Models\{MaintenanceTicket, Property, PropertyAssignment, Tenant, Unit, User};
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceTicketService
{
    private const STAFF_MUTABLE_FIELDS = ['priority', 'title', 'description'];
    private const OWNER_MANAGER_MUTABLE_FIELDS = [
        'property_id', 'unit_id', 'tenant_id', 'priority', 'title', 'description',
        'estimated_cost', 'actual_cost', 'charged_to',
    ];

    private const NEXT = [
        'submitted' => ['reviewed', 'rejected'], 'reviewed' => ['assigned', 'rejected'],
        'assigned' => ['in_progress', 'rejected'], 'in_progress' => ['waiting', 'resolved', 'rejected'],
        'waiting' => ['in_progress', 'resolved'], 'resolved' => ['closed'], 'closed' => [], 'rejected' => [],
    ];

    public function create(array $data, User $actor): MaintenanceTicket
    {
        return DB::transaction(function () use ($data, $actor) {
            $data = $this->validateActorProperty($data, $actor);
            $data = $this->validateGraph($data);
            $ticket = MaintenanceTicket::create([...$data, 'submitted_by' => $actor->id, 'status' => MaintenanceTicketStatus::Submitted]);
            $this->validateCosts($ticket, $ticket->actual_cost, $ticket->charged_to);
            $ticket->history()->create(['from_status' => null, 'to_status' => MaintenanceTicketStatus::Submitted, 'changed_by' => $actor->id]);
            app(AuditLogger::class)->maintenanceCreated($actor, $ticket);
            return $ticket;
        });
    }

    public function update(MaintenanceTicket $ticket, array $data, User $actor): MaintenanceTicket
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $t = MaintenanceTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->assertActorContext($actor, $t->workspace_id);
            $this->assertEditable($t);
            $role = $this->role($actor, $t->workspace_id);
            $this->assertMutableFields($data, $role);
            if (! ($role === WorkspaceMemberRole::Owner || ($role === WorkspaceMemberRole::Manager && $this->propertyAssigned($actor->id, $t->workspace_id, $t->property_id)) || ($role === WorkspaceMemberRole::Staff && $t->submitted_by === $actor->id && $t->status === MaintenanceTicketStatus::Submitted && $this->propertyAssigned($actor->id, $t->workspace_id, $t->property_id)))) {
                throw ValidationException::withMessages(['ticket' => 'Anda tidak berwenang mengubah tiket ini.']);
            }
            $oldValues = app(AuditLogger::class)->maintenanceValues($t);
            if ($role !== WorkspaceMemberRole::Staff) {
                $merged = array_merge($t->getAttributes(), $data, ['workspace_id' => $t->workspace_id]);
                $this->validateActorProperty($merged, $actor, true);
                $this->validateGraph($merged);
                $t->tenant_id = $merged['tenant_id'] ?? null;
                $this->validateCosts($t, $merged['actual_cost'] ?? null, isset($merged['charged_to']) && $merged['charged_to'] !== null ? MaintenanceTicketChargeTo::from($merged['charged_to']) : null);
            }
            $t->update($data);
            app(AuditLogger::class)->maintenanceUpdated($actor, $t, $oldValues, app(AuditLogger::class)->maintenanceValues($t));
            return $t->fresh();
        });
    }

    private function assertMutableFields(array $data, ?WorkspaceMemberRole $role): void
    {
        $allowed = $role === WorkspaceMemberRole::Staff
            ? self::STAFF_MUTABLE_FIELDS
            : self::OWNER_MANAGER_MUTABLE_FIELDS;
        $unexpected = array_values(array_diff(array_keys($data), $allowed));

        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'fields' => 'Hanya field tiket yang dapat diubah yang boleh dikirim.',
            ]);
        }
    }

    public function validateActorProperty(array $data, User $actor, bool $destination = false): array
    {
        $this->assertActorContext($actor, (int) $data['workspace_id']);
        $role = $this->role($actor, (int) $data['workspace_id']);
        if (! $role || $actor->status !== UserStatus::Active || $actor->platform_role === PlatformRole::SuperAdmin) {
            throw ValidationException::withMessages(['property_id' => 'Akses workspace tidak valid.']);
        }
        $assigned = PropertyAssignment::where('workspace_id', $data['workspace_id'])->where('property_id', $data['property_id'])->where('user_id', $actor->id)->exists();
        if ($role !== WorkspaceMemberRole::Owner && ! $assigned) {
            throw ValidationException::withMessages(['property_id' => 'Properti ini tidak ditugaskan kepada Anda.']);
        }
        return $data;
    }

    public function validateGraph(array $data): array
    {
        $property = Property::where('workspace_id', $data['workspace_id'])->find($data['property_id']);
        if (! $property || $property->trashed() || $property->status !== PropertyStatus::Active) throw ValidationException::withMessages(['property_id' => 'Properti aktif wajib dipilih.']);
        $unit = Unit::where('workspace_id', $data['workspace_id'])->find($data['unit_id']);
        if (! $unit || $unit->trashed() || $unit->property_id !== (int) $property->id) throw ValidationException::withMessages(['unit_id' => 'Unit tidak sesuai dengan properti.']);
        if (! empty($data['tenant_id'])) {
            $tenant = Tenant::where('workspace_id', $data['workspace_id'])->find($data['tenant_id']);
            if (! $tenant || $tenant->trashed() || $tenant->status->value !== 'active' || (int) $tenant->unit_id !== (int) $unit->id) throw ValidationException::withMessages(['tenant_id' => 'Tenant aktif harus berada pada unit yang dipilih.']);
        }
        return $data;
    }

    public function assign(MaintenanceTicket $ticket, int $userId, User $actor): MaintenanceTicket
    {
        return DB::transaction(function () use ($ticket, $userId, $actor) {
        $t = MaintenanceTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->assertActorContext($actor, $t->workspace_id);
            $this->validateGraph($t->getAttributes());
            if (! in_array($t->status, [MaintenanceTicketStatus::Reviewed, MaintenanceTicketStatus::Assigned, MaintenanceTicketStatus::InProgress, MaintenanceTicketStatus::Waiting], true)) {
                throw ValidationException::withMessages(['assigned_to' => 'Tiket harus berstatus reviewed atau sudah ditugaskan.']);
            }
            $actorRole = $this->role($actor, $t->workspace_id);
            if (! in_array($actorRole, [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager], true) || ($actorRole === WorkspaceMemberRole::Manager && ! $this->propertyAssigned($actor->id, $t->workspace_id, $t->property_id))) throw ValidationException::withMessages(['assigned_to' => 'Anda tidak berwenang menugaskan tiket ini.']);
            $u = User::find($userId);
            $eligible = $u && $u->status === UserStatus::Active && $u->platform_role !== PlatformRole::SuperAdmin && $this->role($u, $t->workspace_id) !== null && in_array($this->role($u, $t->workspace_id), [WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true) && $this->propertyAssigned($u->id, $t->workspace_id, $t->property_id);
            if (! $eligible) throw ValidationException::withMessages(['assigned_to' => 'Petugas aktif harus ditugaskan pada properti ini.']);
            $oldAssignee = $t->assigned_to;
            $t->assigned_to = $userId;
            if ($t->status === MaintenanceTicketStatus::Reviewed) $this->transitionLocked($t, MaintenanceTicketStatus::Assigned, null, $actor);
            else $t->save();
            app(AuditLogger::class)->maintenanceAssignment($actor, $t, $oldAssignee, $userId);
            return $t->fresh();
        });
    }

    public function transition(MaintenanceTicket $ticket, MaintenanceTicketStatus $to, ?string $reason, User $actor): MaintenanceTicket
    {
        return DB::transaction(function () use ($ticket, $to, $reason, $actor) { $t = MaintenanceTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail(); return $this->transitionLocked($t, $to, $reason, $actor)->fresh(); });
    }

    private function transitionLocked(MaintenanceTicket $ticket, MaintenanceTicketStatus $to, ?string $reason, User $actor): MaintenanceTicket
    {
        $this->assertActorContext($actor, $ticket->workspace_id);
        $this->validateGraph($ticket->getAttributes());
        $role = $this->role($actor, $ticket->workspace_id);
        $manager = $role === WorkspaceMemberRole::Manager && $this->propertyAssigned($actor->id, $ticket->workspace_id, $ticket->property_id);
        $staff = $role === WorkspaceMemberRole::Staff && $ticket->assigned_to === $actor->id && $this->propertyAssigned($actor->id, $ticket->workspace_id, $ticket->property_id) && in_array($ticket->status, [MaintenanceTicketStatus::Assigned, MaintenanceTicketStatus::InProgress, MaintenanceTicketStatus::Waiting], true);
        if ($role !== WorkspaceMemberRole::Owner && ! $manager && ! $staff) throw ValidationException::withMessages(['status' => 'Anda tidak berwenang mengubah status tiket ini.']);
        $from = $ticket->status;
        if (! in_array($to->value, self::NEXT[$from->value] ?? [], true)) throw ValidationException::withMessages(['status' => 'Transisi status tidak diizinkan.']);
        if ($to === MaintenanceTicketStatus::Assigned && ($ticket->assigned_to === null || ! $this->eligibleAssignee($ticket))) {
            throw ValidationException::withMessages(['assigned_to' => 'Petugas aktif yang ditugaskan pada properti ini wajib dipilih.']);
        }
        if (in_array($to, [MaintenanceTicketStatus::Rejected, MaintenanceTicketStatus::Waiting], true) && blank($reason)) throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi.']);
        if ($to === MaintenanceTicketStatus::Resolved) $ticket->resolved_at = now();
        if ($to === MaintenanceTicketStatus::Rejected) { $ticket->rejection_reason = $reason; $ticket->resolved_at = null; }
        if ($to === MaintenanceTicketStatus::Waiting) $ticket->waiting_reason = $reason;
        $ticket->status = $to;
        $this->beginStatusMutation($ticket->id);
        MaintenanceTicket::allowStatusMutation(true);
        try { $ticket->save(); } finally { MaintenanceTicket::allowStatusMutation(false); $this->endStatusMutation($ticket->id); }
        $ticket->history()->create(['from_status' => $from, 'to_status' => $to, 'changed_by' => $actor->id, 'reason' => $reason]);
        app(AuditLogger::class)->maintenanceEvent($actor, $ticket, $from, $to);
        return $ticket;
    }

    public function validateCosts(MaintenanceTicket $ticket, mixed $actual, mixed $charge): void
    {
        $charge = is_string($charge) ? MaintenanceTicketChargeTo::from($charge) : $charge;
        if ($actual !== null && $charge === null) throw ValidationException::withMessages(['charged_to' => 'Tujuan biaya wajib diisi.']);
        if ($actual === null && $charge !== null) throw ValidationException::withMessages(['actual_cost' => 'Biaya aktual wajib diisi.']);
        if ($charge === MaintenanceTicketChargeTo::Tenant) {
            $tenant = $ticket->tenant_id ? Tenant::where('workspace_id', $ticket->workspace_id)->find($ticket->tenant_id) : null;
            if (! $tenant || $tenant->status->value !== 'active' || $tenant->trashed()) throw ValidationException::withMessages(['charged_to' => 'Biaya tenant hanya untuk tenant aktif pada tiket.']);
        }
    }

    public function assertEditable(MaintenanceTicket $ticket): void
    { if (in_array($ticket->status, [MaintenanceTicketStatus::Closed, MaintenanceTicketStatus::Rejected], true)) throw ValidationException::withMessages(['ticket' => 'Tiket terminal tidak dapat diubah.']); }

    public function parentIsRestorable(MaintenanceTicket $ticket): bool
    { try { $this->validateGraph($ticket->getAttributes()); } catch (ValidationException) { return false; } return $ticket->assigned_to === null || $this->eligibleAssignee($ticket); }

    private function eligibleAssignee(MaintenanceTicket $t): bool { $u = User::find($t->assigned_to); return $u && $u->status === UserStatus::Active && $u->platform_role !== PlatformRole::SuperAdmin && in_array($this->role($u, $t->workspace_id), [WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true) && $this->propertyAssigned($u->id, $t->workspace_id, $t->property_id); }
    private function propertyAssigned(int $userId, int $workspaceId, int $propertyId): bool { return PropertyAssignment::where('user_id', $userId)->where('workspace_id', $workspaceId)->where('property_id', $propertyId)->exists(); }
    private function role(User $user, int $workspaceId): ?WorkspaceMemberRole { return DB::table('workspace_members')->where('workspace_id', $workspaceId)->where('user_id', $user->id)->where('status', UserStatus::Active->value)->value('role') ? WorkspaceMemberRole::from(DB::table('workspace_members')->where('workspace_id', $workspaceId)->where('user_id', $user->id)->where('status', UserStatus::Active->value)->value('role')) : null; }

    public function delete(MaintenanceTicket $ticket, User $actor): void
    {
        DB::transaction(function () use ($ticket, $actor): void {
            $t = MaintenanceTicket::withTrashed()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->assertActorContext($actor, $t->workspace_id);
            if ($this->role($actor, $t->workspace_id) !== WorkspaceMemberRole::Owner || $t->trashed() || ! in_array($t->status, [MaintenanceTicketStatus::Closed, MaintenanceTicketStatus::Rejected], true)) throw ValidationException::withMessages(['ticket' => 'Tiket ini tidak dapat dihapus.']);
            $t->delete();
            app(AuditLogger::class)->maintenanceDeleted($actor, $t);
        });
    }

    public function restore(MaintenanceTicket $ticket, User $actor): void
    {
        DB::transaction(function () use ($ticket, $actor): void {
            $t = MaintenanceTicket::withTrashed()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->assertActorContext($actor, $t->workspace_id);
            if ($this->role($actor, $t->workspace_id) !== WorkspaceMemberRole::Owner || ! $t->trashed() || ! in_array($t->status, [MaintenanceTicketStatus::Closed, MaintenanceTicketStatus::Rejected], true) || ! $this->parentIsRestorable($t)) throw ValidationException::withMessages(['ticket' => 'Tiket ini tidak dapat dipulihkan.']);
            $t->restore();
            app(AuditLogger::class)->maintenanceRestored($actor, $t);
        });
    }

    private function assertActorContext(User $actor, int $workspaceId): void
    {
        if ((auth()->check() && auth()->id() !== $actor->id) || (session()->has('current_workspace_id') && (int) session('current_workspace_id') !== $workspaceId) || $actor->status !== UserStatus::Active || $actor->platform_role === PlatformRole::SuperAdmin || ! $this->role($actor, $workspaceId)) throw ValidationException::withMessages(['ticket' => 'Konteks pengguna atau workspace tidak valid.']);
    }

    private function beginStatusMutation(int $ticketId): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') DB::table('maintenance_ticket_status_mutation_guards')->insert(['maintenance_ticket_id' => $ticketId]);
        elseif ($driver === 'pgsql') DB::statement("SELECT set_config('rentara.maintenance_ticket_status_id', ?, true)", [(string) $ticketId]);
        elseif (in_array($driver, ['mysql', 'mariadb'], true)) DB::statement('SET @rentara_maintenance_ticket_status_id = '.$ticketId);
    }

    private function endStatusMutation(int $ticketId): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') DB::table('maintenance_ticket_status_mutation_guards')->where('maintenance_ticket_id', $ticketId)->delete();
        elseif ($driver === 'pgsql') DB::statement("SELECT set_config('rentara.maintenance_ticket_status_id', '', true)");
        elseif (in_array($driver, ['mysql', 'mariadb'], true)) DB::statement('SET @rentara_maintenance_ticket_status_id = NULL');
    }
}
