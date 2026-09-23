<?php

namespace App\Policies;

use App\Enums\{MaintenanceTicketStatus, PlatformRole, PropertyStatus, UserStatus, WorkspaceMemberRole};
use App\Models\{MaintenanceTicket, PropertyAssignment, User, Workspace};

class MaintenanceTicketPolicy
{
    private function role(User $user, Workspace $workspace): ?WorkspaceMemberRole
    { if ($user->platform_role === PlatformRole::SuperAdmin) return null; return $workspace->members()->where('user_id', $user->id)->where('status', UserStatus::Active->value)->first()?->role; }
    private function property(User $user, MaintenanceTicket $ticket): bool
    { return PropertyAssignment::where('workspace_id', $ticket->workspace_id)->where('property_id', $ticket->property_id)->where('user_id', $user->id)->exists(); }
    private function parent(MaintenanceTicket $ticket): bool
    { return $ticket->property && ! $ticket->property->trashed() && $ticket->property->status === PropertyStatus::Active && $ticket->unit && ! $ticket->unit->trashed() && $ticket->unit->property_id === $ticket->property_id; }
    public function viewAny(User $user, Workspace $workspace): bool { return $this->role($user, $workspace) !== null; }
    public function viewAll(User $user, Workspace $workspace): bool { return $this->role($user, $workspace) === WorkspaceMemberRole::Owner; }
    public function view(User $user, MaintenanceTicket $ticket): bool { $r=$this->role($user,$ticket->workspace); return $this->parent($ticket) && ($r===WorkspaceMemberRole::Owner || in_array($r,[WorkspaceMemberRole::Manager,WorkspaceMemberRole::Staff],true) && $this->property($user,$ticket)); }
    public function create(User $user, Workspace $workspace): bool { return in_array($this->role($user,$workspace),[WorkspaceMemberRole::Owner,WorkspaceMemberRole::Manager,WorkspaceMemberRole::Staff],true); }
    public function update(User $user, MaintenanceTicket $ticket): bool
    { if (in_array($ticket->status,[MaintenanceTicketStatus::Closed,MaintenanceTicketStatus::Rejected],true)) return false; $r=$this->role($user,$ticket->workspace); return $r===WorkspaceMemberRole::Owner || $r===WorkspaceMemberRole::Manager && $this->property($user,$ticket) || $r===WorkspaceMemberRole::Staff && $ticket->submitted_by===$user->id && $ticket->status===MaintenanceTicketStatus::Submitted && $this->property($user,$ticket); }
    public function transition(User $user, MaintenanceTicket $ticket): bool
    { if (! $this->parent($ticket)) return false; $r=$this->role($user,$ticket->workspace); return $r===WorkspaceMemberRole::Owner || $r===WorkspaceMemberRole::Manager && $this->property($user,$ticket) || $r===WorkspaceMemberRole::Staff && $ticket->assigned_to===$user->id && in_array($ticket->status,[MaintenanceTicketStatus::Assigned,MaintenanceTicketStatus::InProgress,MaintenanceTicketStatus::Waiting],true); }
    public function assign(User $user, MaintenanceTicket $ticket): bool { $r=$this->role($user,$ticket->workspace); return $this->parent($ticket) && ($r===WorkspaceMemberRole::Owner || $r===WorkspaceMemberRole::Manager && $this->property($user,$ticket)); }
    public function uploadPhoto(User $user, MaintenanceTicket $ticket): bool
    { if (! $this->parent($ticket) || in_array($ticket->status,[MaintenanceTicketStatus::Closed,MaintenanceTicketStatus::Rejected],true)) return false; $r=$this->role($user,$ticket->workspace); return $r===WorkspaceMemberRole::Owner || $r===WorkspaceMemberRole::Manager && $this->property($user,$ticket) || $r===WorkspaceMemberRole::Staff && $this->property($user,$ticket) && ($ticket->submitted_by===$user->id || $ticket->assigned_to===$user->id); }
    public function delete(User $user, MaintenanceTicket $ticket): bool { return $this->role($user,$ticket->workspace)===WorkspaceMemberRole::Owner && in_array($ticket->status,[MaintenanceTicketStatus::Closed,MaintenanceTicketStatus::Rejected],true) && ! $ticket->trashed(); }
    public function restore(User $user, MaintenanceTicket $ticket): bool { return $this->role($user,$ticket->workspace)===WorkspaceMemberRole::Owner && $ticket->trashed() && in_array($ticket->status,[MaintenanceTicketStatus::Closed,MaintenanceTicketStatus::Rejected],true); }
}
