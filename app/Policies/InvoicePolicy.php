<?php

namespace App\Policies;

use App\Enums\{PlatformRole, UserStatus, WorkspaceMemberRole};
use App\Models\{Invoice, User, Workspace};

class InvoicePolicy
{
    private function role(User $user, Workspace $workspace): ?WorkspaceMemberRole
    {
        if ($user->platform_role === PlatformRole::SuperAdmin) return null;
        return $workspace->members()->where('user_id', $user->id)->where('status', UserStatus::Active->value)->first()?->role;
    }
    private function assigned(User $user, Invoice $invoice): bool
    { return $invoice->property->assignments()->where('workspace_id', $invoice->workspace_id)->where('user_id', $user->id)->exists(); }
    public function viewAny(User $user, Workspace $workspace): bool { return $this->role($user, $workspace) !== null; }
    public function view(User $user, Invoice $invoice): bool
    { $role = $this->role($user, $invoice->workspace); return $role === WorkspaceMemberRole::Owner || in_array($role, [WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true) && $this->assigned($user, $invoice); }
    public function create(User $user, Workspace $workspace): bool
    { $role = $this->role($user, $workspace); return in_array($role, [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager], true); }
    public function update(User $user, Invoice $invoice): bool
    {
        if ($invoice->status->value !== 'unpaid') return false;
        $role = $this->role($user, $invoice->workspace);
        return $role === WorkspaceMemberRole::Owner || $role === WorkspaceMemberRole::Manager && $this->assigned($user, $invoice);
    }
    public function pay(User $user, Invoice $invoice): bool
    { $role = $this->role($user, $invoice->workspace); return in_array($role, [WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff], true) && ($role === WorkspaceMemberRole::Owner || $this->assigned($user, $invoice)); }
}
