<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\RentaraDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_seed_creates_demo_users_workspace_and_memberships(): void
    {
        $this->seed(RentaraDemoSeeder::class);

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseCount('workspace_members', 3);

        $admin = User::where('email', 'admin@rentara.test')->firstOrFail();
        $owner = User::where('email', 'owner@rentara.test')->firstOrFail();
        $manager = User::where('email', 'manager@rentara.test')->firstOrFail();
        $staff = User::where('email', 'staff@rentara.test')->firstOrFail();

        $this->assertSame(PlatformRole::SuperAdmin, $admin->platform_role);
        $this->assertNull($owner->platform_role);
        $this->assertNull($manager->platform_role);
        $this->assertNull($staff->platform_role);

        foreach ([$admin, $owner, $manager, $staff] as $user) {
            $this->assertSame(UserStatus::Active, $user->status);
            $this->assertNotNull($user->email_verified_at);
            $this->assertSame(mb_strtolower($user->email), $user->email);
        }

        // Platform boundary: super_admin holds no workspace membership.
        $this->assertDatabaseMissing('workspace_members', ['user_id' => $admin->id]);

        $workspace = Workspace::where('slug', 'rentara-demo-property')->firstOrFail();
        $this->assertSame('Rentara Demo Property', $workspace->name);
        $this->assertSame($owner->id, $workspace->owner_id);

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceMemberRole::Owner->value,
            'status' => UserStatus::Active->value,
        ]);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $manager->id,
            'role' => WorkspaceMemberRole::Manager->value,
            'status' => UserStatus::Active->value,
        ]);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $staff->id,
            'role' => WorkspaceMemberRole::Staff->value,
            'status' => UserStatus::Active->value,
        ]);
    }

    public function test_rerun_does_not_duplicate_demo_data(): void
    {
        $this->seed(RentaraDemoSeeder::class);
        $this->seed(RentaraDemoSeeder::class);

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseCount('workspace_members', 3);
    }

    public function test_demo_owner_can_login(): void
    {
        $this->seed(RentaraDemoSeeder::class);

        $password = (string) (env('DEMO_PASSWORD', 'password') ?: 'password');

        Volt::test('pages.auth.login')
            ->set('form.email', 'owner@rentara.test')
            ->set('form.password', $password)
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('app.dashboard', absolute: false));

        $this->assertAuthenticatedAs(User::where('email', 'owner@rentara.test')->firstOrFail());
        $this->assertSame(1, WorkspaceMember::whereHas('user', fn ($query) => $query->where('email', 'owner@rentara.test'))->count());
    }
}
