<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\OrganizationRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OrganizationInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_send_a_hashed_expiring_invitation(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->createOrganizationOwner();

        $this->actingAs($owner)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => ' Future.Member@Example.Test ',
                'role' => 'manager',
            ])
            ->assertSessionHas('status', 'organization-invitation-sent');

        $invitation = OrganizationInvitation::query()->firstOrFail();

        $this->assertSame('future.member@example.test', $invitation->email);
        $this->assertSame(OrganizationRole::Manager, $invitation->role);
        $this->assertSame($owner->id, $invitation->invited_by);
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertTrue($invitation->expires_at->isFuture());

        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            function (
                OrganizationInvitationNotification $notification,
                array $channels,
                AnonymousNotifiable $notifiable,
            ) use ($invitation): bool {
                return $notifiable->routes['mail'] === 'future.member@example.test'
                    && hash('sha256', $notification->token) === $invitation->token_hash
                    && $channels === ['mail'];
            },
        );
    }

    public function test_invitation_can_only_be_accepted_by_the_matching_verified_account_once(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->createOrganizationOwner();
        $token = $this->inviteAndGetToken($owner, $organization, 'staff@example.test', 'staff');
        $invitee = User::factory()->create(['email' => 'staff@example.test']);

        $this->actingAs($invitee)
            ->get(route('organization-invitations.show', $token))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Organizations/Invitations/Show')
                ->where('organization.name', $organization->name)
                ->where('role', 'staff'));

        $this->post(route('organization-invitations.accept', $token))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'organization-invitation-accepted');

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $invitee->id)
            ->firstOrFail();

        $this->assertSame(OrganizationRole::Staff, $membership->role);
        $this->assertNotNull($membership->accepted_at);
        $this->assertDatabaseHas('organization_invitations', [
            'token_hash' => hash('sha256', $token),
            'accepted_by' => $invitee->id,
        ]);

        $this->post(route('organization-invitations.accept', $token))->assertStatus(410);
    }

    public function test_invitation_link_is_hidden_from_an_account_with_a_different_email(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->createOrganizationOwner();
        $token = $this->inviteAndGetToken($owner, $organization, 'intended@example.test', 'staff');
        $otherUser = User::factory()->create(['email' => 'other@example.test']);

        $this->actingAs($otherUser)
            ->get(route('organization-invitations.show', $token))
            ->assertNotFound();

        $this->post(route('organization-invitations.accept', $token))->assertNotFound();
        $this->assertDatabaseCount('organization_memberships', 1);
        $this->assertDatabaseHas('organization_invitations', [
            'token_hash' => hash('sha256', $token),
            'accepted_at' => null,
        ]);
    }

    public function test_manager_can_only_invite_staff_and_cannot_remove_a_manager(): void
    {
        Notification::fake();
        [, $organization] = $this->createOrganizationOwner();
        $manager = User::factory()->create();
        $managerMembership = $organization->memberships()->create([
            'user_id' => $manager->id,
            'role' => OrganizationRole::Manager,
            'accepted_at' => now(),
        ]);
        $otherManager = User::factory()->create();
        $otherManagerMembership = $organization->memberships()->create([
            'user_id' => $otherManager->id,
            'role' => OrganizationRole::Manager,
            'accepted_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => 'new.manager@example.test',
                'role' => 'manager',
            ])
            ->assertSessionHasErrors('role');

        $this->post(route('organizations.invitations.store', $organization), [
            'email' => 'new.staff@example.test',
            'role' => 'staff',
        ])->assertSessionHas('status', 'organization-invitation-sent');

        $this->delete(route('organizations.members.destroy', [$organization, $otherManagerMembership]))
            ->assertForbidden();

        $this->assertDatabaseHas('organization_memberships', ['id' => $managerMembership->id]);
        $this->assertDatabaseHas('organization_memberships', ['id' => $otherManagerMembership->id]);
    }

    public function test_non_members_cannot_view_or_invite_organization_members(): void
    {
        [, $organization] = $this->createOrganizationOwner();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('organizations.members.index', $organization))
            ->assertForbidden();

        $this->post(route('organizations.invitations.store', $organization), [
            'email' => 'outsider.invitee@example.test',
            'role' => 'staff',
        ])->assertForbidden();

        $this->assertDatabaseCount('organization_invitations', 0);
    }

    public function test_revoked_and_expired_invitation_tokens_cannot_be_used(): void
    {
        Notification::fake();
        [$owner, $organization] = $this->createOrganizationOwner();
        $revokedToken = $this->inviteAndGetToken($owner, $organization, 'revoked@example.test', 'staff');
        $revokedInvitation = OrganizationInvitation::query()
            ->where('token_hash', hash('sha256', $revokedToken))
            ->firstOrFail();

        $this->actingAs($owner)
            ->delete(route('organizations.invitations.destroy', [$organization, $revokedInvitation]))
            ->assertSessionHas('status', 'organization-invitation-revoked');

        $expiredToken = $this->inviteAndGetToken($owner, $organization, 'expired@example.test', 'staff');
        OrganizationInvitation::query()
            ->where('token_hash', hash('sha256', $expiredToken))
            ->update(['expires_at' => now()->subMinute()]);

        $revokedInvitee = User::factory()->create(['email' => 'revoked@example.test']);
        $expiredInvitee = User::factory()->create(['email' => 'expired@example.test']);

        $this->actingAs($revokedInvitee)
            ->get(route('organization-invitations.show', $revokedToken))
            ->assertGone();

        $this->post(route('organization-invitations.accept', $revokedToken))->assertGone();

        $this->actingAs($expiredInvitee)
            ->get(route('organization-invitations.show', $expiredToken))
            ->assertGone();

        $this->post(route('organization-invitations.accept', $expiredToken))->assertGone();
        $this->assertDatabaseCount('organization_memberships', 1);
    }

    /** @return array{User, Organization} */
    private function createOrganizationOwner(): array
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->memberships()->create([
            'user_id' => $owner->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);

        return [$owner, $organization];
    }

    private function inviteAndGetToken(User $owner, Organization $organization, string $email, string $role): string
    {
        $this->actingAs($owner)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => $email,
                'role' => $role,
            ]);

        $token = null;

        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            function (OrganizationInvitationNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->assertIsString($token);

        return $token;
    }
}
