<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/** Internal, allowlist-only audit writer. It never accepts request payloads. */
class AuditLogger
{
    public const REGISTRATION = 'identity.registered';
    public const WORKSPACE_CREATED = 'workspace.created';
    public const LOGIN_SUCCEEDED = 'auth.login_succeeded';
    /** Every rejected credential attempt uses this event to avoid account-state enumeration. */
    public const LOGIN_DENIED = 'auth.login_denied';
    public const LOGOUT = 'auth.logout';
    public const MEMBER_ADDED = 'workspace.member_added';
    public const MEMBER_ROLE_CHANGED = 'workspace.member_role_changed';
    public const MEMBER_STATUS_CHANGED = 'workspace.member_status_changed';
    public const MEMBER_REMOVED = 'workspace.member_removed';
    public const PLATFORM_DASHBOARD_ACCESSED = 'platform.dashboard_accessed';

    public function registration(User $user, ?AuditRequestContext $context = null): void
    {
        $this->write(self::REGISTRATION, $user, null, $user, null, ['user_id' => $user->id], $context);
    }

    public function workspaceCreated(User $user, Workspace $workspace, ?AuditRequestContext $context = null): void
    {
        $this->write(self::WORKSPACE_CREATED, $user, $workspace, $workspace, null, ['workspace_id' => $workspace->id], $context);
    }

    public function loginSucceeded(User $user, AuditRequestContext $context): void
    {
        $this->write(self::LOGIN_SUCCEEDED, $user, null, $user, null, ['last_login_at' => $this->timestamp($user->last_login_at)], $context);
    }

    public function loginDenied(AuditRequestContext $context): void
    {
        $this->write(self::LOGIN_DENIED, null, null, null, null, null, $context);
    }

    public function logout(User $user, ?AuditRequestContext $context = null): void
    {
        $this->write(self::LOGOUT, $user, null, $user, null, null, $context);
    }

    public function memberAdded(?User $actor, WorkspaceMember $member, ?AuditRequestContext $context = null): void
    {
        $this->write(self::MEMBER_ADDED, $actor, $member->workspace, $member, null, $this->memberValues($member), $context);
    }

    public function memberRoleChanged(?User $actor, WorkspaceMember $member, WorkspaceMemberRole $oldRole, ?AuditRequestContext $context = null): void
    {
        $this->write(self::MEMBER_ROLE_CHANGED, $actor, $member->workspace, $member, ['role' => $oldRole->value], ['role' => $member->role->value], $context);
    }

    public function memberStatusChanged(?User $actor, WorkspaceMember $member, UserStatus $oldStatus, ?AuditRequestContext $context = null): void
    {
        $this->write(self::MEMBER_STATUS_CHANGED, $actor, $member->workspace, $member, ['status' => $oldStatus->value], ['status' => $member->status->value], $context);
    }

    public function memberRemoved(?User $actor, WorkspaceMember $member, ?AuditRequestContext $context = null): void
    {
        $this->write(self::MEMBER_REMOVED, $actor, $member->workspace, $member, $this->memberValues($member), null, $context);
    }

    public function platformDashboardAccessed(User $user, AuditRequestContext $context): void
    {
        $this->write(self::PLATFORM_DASHBOARD_ACCESSED, $user, null, $user, null, null, $context);
    }

    private function memberValues(WorkspaceMember $member): array
    {
        return ['member_id' => $member->id, 'user_id' => $member->user_id, 'role' => $member->role->value, 'status' => $member->status->value];
    }

    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    private function timestamp(?DateTimeInterface $value): ?string
    {
        return $value?->format(DateTimeInterface::ATOM);
    }

    private function write(string $event, ?User $user, ?Workspace $workspace, ?Model $auditable, ?array $oldValues, ?array $newValues, ?AuditRequestContext $context = null): void
    {
        $this->assertSafeValues($oldValues);
        $this->assertSafeValues($newValues);

        $attributes = [
            'user_id' => $user?->id,
            'workspace_id' => $workspace?->id,
            'event' => $event,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $context?->ipAddress,
            'user_agent' => $context?->userAgent,
        ];
        AuditLog::assertPersistable($attributes, $oldValues, $newValues);

        // This is the sole trusted persistence path; model mass assignment is never bypassed.
        DB::table('audit_logs')->insert([
            ...$attributes,
            'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => $newValues === null ? null : json_encode($newValues, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    /** Reject secret-bearing keys rather than risking a partial or misleading record. */
    public function assertSafeValues(?array $values): void
    {
        foreach ($values ?? [] as $key => $value) {
            if (! in_array($key, ['user_id', 'workspace_id', 'member_id', 'role', 'status', 'last_login_at'], true)) {
                throw new InvalidArgumentException('Only allowlisted audit values can be recorded.');
            }
            if (preg_match('/password|token|remember|api[_-]?key|credential|document|content|path/i', (string) $key) || (is_string($value) && preg_match('/password|token|remember|api[_-]?key|credential|document|content|path|secret|bearer/i', $value))) {
                throw new InvalidArgumentException('Secret or raw document fields cannot be audited.');
            }
            if (in_array($key, ['user_id', 'workspace_id', 'member_id'], true) && (! is_int($value) || $value < 1)) {
                throw new InvalidArgumentException('Audit identifiers must be positive integers.');
            }
            if ($key === 'role' && (! is_string($value) || ! in_array($value, array_column(WorkspaceMemberRole::cases(), 'value'), true))) {
                throw new InvalidArgumentException('Audit roles must be valid workspace roles.');
            }
            if ($key === 'status' && (! is_string($value) || ! in_array($value, array_column(UserStatus::cases(), 'value'), true))) {
                throw new InvalidArgumentException('Audit statuses must be valid user statuses.');
            }
            if ($key === 'last_login_at' && $value !== null && (! is_string($value) || DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) === false)) {
                throw new InvalidArgumentException('Audit timestamps must be ISO-8601 values.');
            }
        }
    }
}
