<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->findInvitation($token);
        $this->ensureInvitationIsForUser($invitation, $request);

        return Inertia::render('Organizations/Invitations/Show', [
            'organization' => $invitation->organization->only('id', 'name'),
            'role' => $invitation->role->value,
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        DB::transaction(function () use ($request, $token): void {
            $invitation = OrganizationInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureInvitationIsActive($invitation);
            $this->ensureInvitationIsForUser($invitation, $request);

            $membership = OrganizationMembership::query()
                ->where('organization_id', $invitation->organization_id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            abort_if($membership?->accepted_at !== null, 409, 'You are already a member of this organization.');

            if ($membership === null) {
                $membership = new OrganizationMembership;
                $membership->organization_id = $invitation->organization_id;
                $membership->user_id = $request->user()->id;
            }

            $membership->role = $invitation->role;
            $membership->invited_by = $invitation->invited_by;
            $membership->accepted_at = now();
            $membership->save();

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_by' => $request->user()->id,
            ])->save();

        }, attempts: 3);

        return to_route('dashboard')->with('status', 'organization-invitation-accepted');
    }

    private function findInvitation(string $token): OrganizationInvitation
    {
        $invitation = OrganizationInvitation::query()
            ->with('organization:id,name')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        $this->ensureInvitationIsActive($invitation);

        return $invitation;
    }

    private function ensureInvitationIsActive(OrganizationInvitation $invitation): void
    {
        abort_if($invitation->accepted_at !== null || $invitation->revoked_at !== null, 410);
        abort_if($invitation->expires_at->isPast(), 410);
    }

    private function ensureInvitationIsForUser(OrganizationInvitation $invitation, Request $request): void
    {
        abort_unless(
            hash_equals(mb_strtolower($invitation->email), mb_strtolower($request->user()->email)),
            404,
        );
    }
}
