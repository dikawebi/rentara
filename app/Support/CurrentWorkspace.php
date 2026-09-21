<?php

namespace App\Support;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;

class CurrentWorkspace
{
    public const SESSION_KEY = 'current_workspace_id';

    private ?Workspace $workspace = null;

    public function resolve(Request $request): ?Workspace
    {
        if ($this->workspace) {
            return $this->workspace;
        }
        $user = $request->user();
        if (! $user || $user->status !== UserStatus::Active || $user->platform_role === PlatformRole::SuperAdmin) {
            return null;
        }
        $id = $request->session()->get(self::SESSION_KEY);
        $membership = WorkspaceMember::query()->with('workspace')->where('user_id', $user->id)->where('status', UserStatus::Active)->whereHas('workspace', fn ($q) => $q->where('status', WorkspaceStatus::Active))
            ->when($id, fn ($q) => $q->where('workspace_id', $id), fn ($q) => $q->orderBy('workspace_id'))->first();
        if (! $membership) {
            $request->session()->forget(self::SESSION_KEY);
            $membership = WorkspaceMember::query()->with('workspace')->where('user_id', $user->id)->where('status', UserStatus::Active)->whereHas('workspace', fn ($q) => $q->where('status', WorkspaceStatus::Active))->orderBy('workspace_id')->first();
        }
        if (! $membership) {
            return null;
        }
        $request->session()->put(self::SESSION_KEY, $membership->workspace_id);

        return $this->workspace = $membership->workspace;
    }

    public function switch(User $user, int $workspaceId): bool
    {
        return WorkspaceMember::query()->where('user_id', $user->id)->where('workspace_id', $workspaceId)->where('status', UserStatus::Active)
            ->whereHas('workspace', fn ($q) => $q->where('status', WorkspaceStatus::Active))->exists();
    }
}
