<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteOrganizationMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\OrganizationRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationMembersController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('viewMembers', $organization);

        $actorRole = $request->user()->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->whereNotNull('accepted_at')
            ->first()?->role;
        $actorRole = $actorRole instanceof OrganizationRole ? $actorRole->value : $actorRole;

        $members = $organization->memberships()
            ->whereNotNull('accepted_at')
            ->with('user:id,name,email')
            ->oldest()
            ->get()
            ->map(fn (OrganizationMembership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'can_remove' => ($actorRole === OrganizationRole::Owner->value
                    && $membership->role !== OrganizationRole::Owner)
                    || ($actorRole === OrganizationRole::Manager->value
                        && $membership->role === OrganizationRole::Staff),
            ])
            ->values();

        $invitations = $organization->invitations()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get()
            ->map(fn (OrganizationInvitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'can_revoke' => $actorRole === OrganizationRole::Owner->value
                    || ($actorRole === OrganizationRole::Manager->value
                        && $invitation->role === OrganizationRole::Staff),
            ])
            ->values();

        return Inertia::render('Organizations/Members/Index', [
            'organization' => $organization->only('id', 'name'),
            'members' => $members,
            'invitations' => $invitations,
            'inviteRoles' => $actorRole === OrganizationRole::Owner->value
                ? [OrganizationRole::Manager->value, OrganizationRole::Staff->value]
                : [OrganizationRole::Staff->value],
        ]);
    }

    public function storeInvitation(InviteOrganizationMemberRequest $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validated();
        $role = OrganizationRole::from($validated['role']);

        $token = Str::random(64);
        $expiresAt = now()->addDays(7);

        $invitation = DB::transaction(function () use ($request, $organization, $validated, $role, $token, $expiresAt): OrganizationInvitation {
            $lockedOrganization = Organization::query()
                ->lockForUpdate()
                ->findOrFail($organization->id);

            Gate::authorize('inviteRole', [$lockedOrganization, $role]);

            $existingUser = User::query()
                ->whereRaw('LOWER(email) = ?', [$validated['email']])
                ->first();

            if ($existingUser !== null && $lockedOrganization->memberships()
                ->where('user_id', $existingUser->id)
                ->whereNotNull('accepted_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Pengguna tersebut sudah menjadi anggota organisasi ini.',
                ]);
            }

            $lockedOrganization->invitations()
                ->where('email', $validated['email'])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $lockedOrganization->invitations()->create([
                'email' => $validated['email'],
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->id,
                'expires_at' => $expiresAt,
            ]);
        });

        Notification::route('mail', $invitation->email)->notify(
            new OrganizationInvitationNotification($organization->name, $role, $token, $expiresAt),
        );

        return back()->with('status', 'organization-invitation-sent');
    }

    public function destroyMembership(
        Organization $organization,
        OrganizationMembership $membership,
    ): RedirectResponse {
        DB::transaction(function () use ($organization, $membership): void {
            $lockedOrganization = Organization::query()
                ->lockForUpdate()
                ->findOrFail($organization->id);
            $lockedMembership = OrganizationMembership::query()
                ->lockForUpdate()
                ->findOrFail($membership->id);

            Gate::authorize('removeMember', [$lockedOrganization, $lockedMembership]);
            $lockedMembership->delete();
        }, attempts: 3);

        return back()->with('status', 'organization-member-removed');
    }

    public function destroyInvitation(
        Organization $organization,
        OrganizationInvitation $invitation,
    ): RedirectResponse {
        DB::transaction(function () use ($organization, $invitation): void {
            $lockedOrganization = Organization::query()
                ->lockForUpdate()
                ->findOrFail($organization->id);
            $lockedInvitation = OrganizationInvitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            Gate::authorize('revokeInvitation', [$lockedOrganization, $lockedInvitation]);
            $lockedInvitation->forceFill(['revoked_at' => now()])->save();
        }, attempts: 3);

        return back()->with('status', 'organization-invitation-revoked');
    }
}
