<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\OrganizationRole;
use App\Policies\OrganizationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OrganizationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_management_policies_are_not_granted_to_staff_or_platform_admins(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create();
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $organization->memberships()->create([
            'user_id' => $staff->id,
            'role' => OrganizationRole::Staff,
            'accepted_at' => now(),
        ]);

        $policy = new OrganizationPolicy;

        foreach (['manageApplications', 'manageAgreements', 'manageBookings', 'manageInvoices'] as $ability) {
            $this->assertFalse($policy->{$ability}($staff, $organization));
            $this->assertFalse($policy->{$ability}($platformAdmin, $organization));
        }
    }

    public function test_verified_user_can_create_an_organization_and_becomes_its_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->post('/organizations', [
                'name' => 'Kos Mentari Jakarta',
                'role' => 'staff',
                'user_id' => $otherUser->id,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'organization-created');

        $organization = Organization::query()->where('name', 'Kos Mentari Jakarta')->firstOrFail();
        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($user->id, $membership->user_id);
        $this->assertSame(OrganizationRole::Owner, $membership->role);
        $this->assertNotNull($membership->accepted_at);
        $this->assertDatabaseCount('organization_memberships', 1);
    }

    public function test_unverified_users_cannot_create_an_organization(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/organizations', ['name' => 'Belum Terverifikasi'])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('organization_memberships', 0);
    }

    public function test_dashboard_only_lists_the_authenticated_users_accepted_memberships(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Kos Milik Saya']);
        $otherOrganization = Organization::factory()->create(['name' => 'Kos Orang Lain']);

        $organization->memberships()->create([
            'user_id' => $user->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);
        $otherOrganization->memberships()->create([
            'user_id' => $otherUser->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);
        $organization->memberships()->create([
            'user_id' => User::factory()->create()->id,
            'role' => OrganizationRole::Staff,
            'accepted_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('organizations', 1)
                ->where('organizations.0.name', 'Kos Milik Saya')
                ->where('organizations.0.role', 'owner'));
    }

    public function test_booking_policy_route_is_unique_and_cannot_cross_organizations(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $organization->memberships()->create([
            'user_id' => $user->id,
            'role' => OrganizationRole::Manager,
            'accepted_at' => now(),
        ]);

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->getName() === 'organizations.booking-policy.update');

        $this->assertCount(1, $routes);
        $this->actingAs($user)
            ->patch(route('organizations.booking-policy.update', $otherOrganization), ['booking_expiry_days' => 5])
            ->assertForbidden();
    }
}
