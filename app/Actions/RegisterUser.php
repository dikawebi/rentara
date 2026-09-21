<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\AuditLogger;
use App\Support\AuditRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUser
{
    /** @param array{name:string,email:string,password:string} $attributes */
    public function handle(array $attributes, ?AuditLogger $auditLogger = null, ?AuditRequestContext $context = null): User
    {
        return DB::transaction(function () use ($attributes, $auditLogger, $context) {
            $user = User::create($attributes);
            $workspace = Workspace::create(['owner_id' => $user->id, 'name' => $user->name."'s Workspace", 'slug' => $this->uniqueSlug($user->name)]);
            WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => WorkspaceMemberRole::Owner, 'status' => UserStatus::Active, 'joined_at' => now()]);
            $auditLogger ??= app(AuditLogger::class);
            $auditLogger->registration($user, $context);
            $auditLogger->workspaceCreated($user, $workspace, $context);

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';

        // The database unique index remains authoritative; the random suffix makes collisions retry-safe in practice.
        return $base.'-'.Str::lower(Str::random(10));
    }
}
