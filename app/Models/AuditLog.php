<?php

namespace App\Models;

use App\Support\AuditLogger;
use App\Models\RentalContract;
use App\Models\CheckIn;
use App\Models\CheckOut;
use App\Models\Invoice;
use App\Models\MaintenanceTicket;
use Database\Factories\AuditLogFactory;
use DateTimeImmutable;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Audit records are written only through the internal AuditLogger. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $auditLog): void {
            self::assertNoFingerprintViaModel($auditLog);
            self::assertPersistable($auditLog->getAttributes(), $auditLog->old_values, $auditLog->new_values);
        });

        static::updating(function (self $auditLog): void {
            self::assertNoFingerprintViaModel($auditLog);
            self::assertPersistable($auditLog->getAttributes(), $auditLog->old_values, $auditLog->new_values);
        });
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'user_agent') {
            throw new InvalidArgumentException('Audit fingerprints cannot be set through the model.');
        }

        return parent::setAttribute($key, $value);
    }

    /** Reject fingerprint persistence on all public Eloquent paths; only the query-builder AuditLogger path may write it. */
    private static function assertNoFingerprintViaModel(self $auditLog): void
    {
        if (! $auditLog->exists) {
            if (array_key_exists('user_agent', $auditLog->getAttributes())) {
                throw new InvalidArgumentException('Audit fingerprints cannot be set through the model.');
            }

            return;
        }

        if ($auditLog->isDirty('user_agent')) {
            throw new InvalidArgumentException('Audit fingerprints cannot be set through the model.');
        }
    }

    /** @param array<string, mixed> $attributes @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    public static function assertPersistable(array $attributes, ?array $oldValues, ?array $newValues): void
    {
        if (! in_array($attributes['event'] ?? null, [
            AuditLogger::REGISTRATION, AuditLogger::WORKSPACE_CREATED, AuditLogger::LOGIN_SUCCEEDED,
            AuditLogger::LOGIN_DENIED, AuditLogger::LOGOUT, AuditLogger::MEMBER_ADDED,
            AuditLogger::MEMBER_ROLE_CHANGED, AuditLogger::MEMBER_STATUS_CHANGED,
            AuditLogger::MEMBER_REMOVED, AuditLogger::PLATFORM_DASHBOARD_ACCESSED,
            AuditLogger::CONTRACT_ACTIVATED, AuditLogger::CONTRACT_TERMINATED,
            AuditLogger::CONTRACT_CHECKED_OUT, AuditLogger::CONTRACT_DEPOSIT_SETTLED,
             AuditLogger::CONTRACT_CHECKED_IN,
             AuditLogger::CONTRACT_SUBMITTED, AuditLogger::CONTRACT_CANCELLED,
             AuditLogger::CONTRACT_RENEWED, AuditLogger::CONTRACT_TENANT_ATTACHED,
              AuditLogger::CONTRACT_RESTORED, AuditLogger::CONTRACT_DELETED,
             AuditLogger::INVOICE_CREATED, AuditLogger::INVOICE_UPDATED, AuditLogger::INVOICE_PAYMENT_RECORDED,
             AuditLogger::MAINTENANCE_STATUS_CHANGED,
              AuditLogger::MAINTENANCE_ASSIGNED,
              AuditLogger::MAINTENANCE_CREATED, AuditLogger::MAINTENANCE_UPDATED,
              AuditLogger::MAINTENANCE_DELETED, AuditLogger::MAINTENANCE_RESTORED,
        ], true)) {
            throw new InvalidArgumentException('Audit events must be registered audit event names.');
        }

        foreach (['user_id', 'workspace_id'] as $key) {
            if (isset($attributes[$key]) && (! is_int($attributes[$key]) || $attributes[$key] < 1)) {
                throw new InvalidArgumentException('Audit relation identifiers must be positive integers.');
            }
        }

        $type = $attributes['auditable_type'] ?? null;
        $id = $attributes['auditable_id'] ?? null;
        if (($type === null) !== ($id === null) || ($type !== null && (! in_array($type, [User::class, Workspace::class, WorkspaceMember::class, RentalContract::class, CheckIn::class, CheckOut::class, Invoice::class, MaintenanceTicket::class], true) || ! is_int($id) || $id < 1))) {
            throw new InvalidArgumentException('Audit auditable references must be known model identifiers.');
        }

        foreach ([$oldValues, $newValues] as $values) {
            app(AuditLogger::class)->assertSafeValues($values);
        }

        if (isset($attributes['ip_address']) && filter_var($attributes['ip_address'], FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Audit IP addresses must be valid.');
        }
        if (isset($attributes['user_agent']) && (! is_string($attributes['user_agent']) || preg_match('/^sha256:[a-f0-9]{64}$/', $attributes['user_agent']) !== 1)) {
            throw new InvalidArgumentException('Audit user agents must be normalized fingerprints.');
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
