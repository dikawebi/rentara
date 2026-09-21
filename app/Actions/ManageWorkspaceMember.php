<?php

namespace App\Actions;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\AuditLogger;
use App\Support\AuditRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageWorkspaceMember
{
    public function add(Workspace $workspace, User $user, WorkspaceMemberRole $role, ?User $actor = null, ?AuditRequestContext $context = null): WorkspaceMember
    {
        if ($role === WorkspaceMemberRole::Owner) {
            throw ValidationException::withMessages(['role' => 'Owner memberships cannot be added.']);
        }

        return DB::transaction(function () use ($workspace, $user, $role, $actor, $context) {
            // This lock is shared with promotion so a user cannot be added while becoming platform-only.
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($lockedUser->status !== UserStatus::Active || $lockedUser->platform_role === PlatformRole::SuperAdmin) {
                throw ValidationException::withMessages(['user_id' => 'This user cannot be added to a workspace.']);
            }

            $member = WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $lockedUser->id, 'role' => $role, 'status' => UserStatus::Active, 'joined_at' => now()]);
            app(AuditLogger::class)->memberAdded($actor, $member, $context);

            return $member;
        });
    }

    public function changeRole(WorkspaceMember $member, WorkspaceMemberRole $role, ?User $actor = null, ?AuditRequestContext $context = null): void
    {
        if ($role === WorkspaceMemberRole::Owner) {
            throw ValidationException::withMessages(['role' => 'Owner roles cannot be assigned.']);
        } DB::transaction(function () use ($member, $role, $actor, $context) {
            $lockedMember = WorkspaceMember::query()->lockForUpdate()->findOrFail($member->id);
            $this->guard($lockedMember);
            $oldRole = $lockedMember->role;
            $lockedMember->update(['role' => $role]);
            app(AuditLogger::class)->memberRoleChanged($actor, $lockedMember, $oldRole, $context);
        });
    }

    public function suspend(WorkspaceMember $member, ?User $actor = null, ?AuditRequestContext $context = null): void
    {
        DB::transaction(function () use ($member, $actor, $context) {
            $lockedMember = WorkspaceMember::query()->lockForUpdate()->findOrFail($member->id);
            $this->guard($lockedMember);
            $oldStatus = $lockedMember->status;
            $lockedMember->update(['status' => UserStatus::Suspended]);
            app(AuditLogger::class)->memberStatusChanged($actor, $lockedMember, $oldStatus, $context);
        });
    }

    public function remove(WorkspaceMember $member, ?User $actor = null, ?AuditRequestContext $context = null): void
    {
        DB::transaction(function () use ($member, $actor, $context) {
            $lockedMember = WorkspaceMember::query()->lockForUpdate()->findOrFail($member->id);
            $this->guard($lockedMember);
            app(AuditLogger::class)->memberRemoved($actor, $lockedMember, $context);
            $lockedMember->delete();
        });
    }

    private function guard(WorkspaceMember $member): void
    {
        if ($member->isCanonicalOwner()) {
            throw ValidationException::withMessages(['member' => 'The canonical owner membership cannot be changed.']);
        }
    }
}
