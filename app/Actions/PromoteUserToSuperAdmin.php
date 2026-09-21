<?php

namespace App\Actions;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromoteUserToSuperAdmin
{
    /**
     * Promoting is deliberately blocked rather than repairing legacy workspace data.
     */
    public function handle(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $hasMembership = WorkspaceMember::query()->where('user_id', $lockedUser->id)->lockForUpdate()->exists();
            $ownsWorkspace = Workspace::withTrashed()->where('owner_id', $lockedUser->id)->lockForUpdate()->exists();

            if ($hasMembership || $ownsWorkspace) {
                throw ValidationException::withMessages(['user_id' => 'Users with workspace access cannot be promoted to super admin.']);
            }

            $lockedUser->update(['platform_role' => PlatformRole::SuperAdmin]);

            return $lockedUser;
        });
    }
}
