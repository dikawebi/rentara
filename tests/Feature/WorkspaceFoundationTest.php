<?php

namespace Tests\Feature;

use App\Actions\ManageWorkspaceMember;
use App\Actions\PromoteUserToSuperAdmin;
use App\Actions\RegisterUser;
use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Policies\WorkspaceMemberPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkspaceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function membership(User $user, Workspace $workspace, WorkspaceMemberRole $role = WorkspaceMemberRole::Staff): WorkspaceMember
    {
        return WorkspaceMember::factory()->create(['user_id' => $user->id, 'workspace_id' => $workspace->id, 'role' => $role]);
    }

    public function test_suspended_users_cannot_use_protected_routes(): void
    {
        $this->actingAs(User::factory()->suspended()->create())->get('/app/dashboard')->assertRedirect('/login');
    }

    public function test_inactive_users_cannot_authenticate_or_access_protected_routes(): void
    {
        $user = User::factory()->inactive()->create();
        Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login')->assertHasErrors();
        $this->assertGuest();
        $this->actingAs($user)->get('/app/dashboard')->assertRedirect('/login');
    }

    public function test_workspace_context_selects_first_active_membership_and_clears_stale_selection(): void
    {
        $user = User::factory()->create();
        $first = Workspace::factory()->create();
        $second = Workspace::factory()->create();
        $this->membership($user, $second);
        $this->membership($user, $first);
        $this->actingAs($user)->withSession(['current_workspace_id' => 99999])->get('/app/dashboard')->assertOk()->assertSee($first->name);
        $this->assertSame($first->id, session('current_workspace_id'));
    }

    public function test_deleted_workspace_selection_is_cleared_and_another_active_workspace_is_selected(): void
    {
        $user = User::factory()->create();
        $deleted = Workspace::factory()->create();
        $replacement = Workspace::factory()->create();
        $this->membership($user, $deleted);
        $this->membership($user, $replacement);
        $deleted->delete();
        $this->actingAs($user)->withSession(['current_workspace_id' => $deleted->id])->get('/app/dashboard')->assertOk()->assertSee($replacement->name);
        $this->assertSame($replacement->id, session('current_workspace_id'));
    }

    public function test_switch_rejects_non_member_and_accepts_active_membership(): void
    {
        $user = User::factory()->create();
        $allowed = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $this->membership($user, $allowed);
        $this->actingAs($user)->post(route('app.workspace.switch'), ['workspace_id' => $other->id])->assertNotFound();
        $this->actingAs($user)->post(route('app.workspace.switch'), ['workspace_id' => $allowed->id])->assertRedirect(route('app.dashboard'));
        $this->assertSame($allowed->id, session('current_workspace_id'));
    }

    public function test_member_mutations_are_scoped_to_current_workspace(): void
    {
        $owner = User::factory()->create();
        $current = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->membership($owner, $current, WorkspaceMemberRole::Owner);
        $foreign = WorkspaceMember::factory()->create();
        $this->actingAs($owner)->withSession(['current_workspace_id' => $current->id])->delete(route('app.members.destroy', $foreign))->assertNotFound();
        $this->assertDatabaseHas('workspace_members', ['id' => $foreign->id]);
    }

    public function test_canonical_owner_cannot_be_changed_suspended_or_removed(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $member = $this->membership($owner, $workspace, WorkspaceMemberRole::Owner);
        $manager = app(ManageWorkspaceMember::class);
        foreach (['changeRole', 'suspend', 'remove'] as $method) {
            try {
                $method === 'changeRole' ? $manager->$method($member, WorkspaceMemberRole::Staff) : $manager->$method($member);
                $this->fail('Expected safeguard');
            } catch (ValidationException) {
            }
        }
        $this->assertDatabaseHas('workspace_members', ['id' => $member->id]);
    }

    public function test_super_admin_is_not_a_workspace_bypass(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($admin)->get('/app/dashboard')->assertForbidden();
    }

    public function test_unique_workspace_membership_constraint_is_enforced(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->membership($user, $workspace);
        $this->expectException(QueryException::class);
        $this->membership($user, $workspace);
    }

    public function test_suspended_memberships_and_workspaces_do_not_form_context(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->suspended()->create();
        $this->membership($user, $workspace);
        $this->actingAs($user)->get('/app/dashboard')->assertStatus(409);
        $workspace->update(['status' => WorkspaceStatus::Active]);
        $workspace->members()->first()->update(['status' => UserStatus::Suspended]);
        $this->actingAs($user)->get('/app/dashboard')->assertStatus(409);
    }

    public function test_manager_cannot_manage_another_manager(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->membership($owner, $workspace, WorkspaceMemberRole::Owner);
        $manager = User::factory()->create();
        $target = User::factory()->create();
        $this->membership($manager, $workspace, WorkspaceMemberRole::Manager);
        $targetMembership = $this->membership($target, $workspace, WorkspaceMemberRole::Manager);
        $this->actingAs($manager)->withSession(['current_workspace_id' => $workspace->id])->delete(route('app.members.destroy', $targetMembership))->assertForbidden();
    }

    public function test_deleted_workspace_selection_returns_no_workspace_when_no_valid_membership_remains(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->membership($user, $workspace);
        $workspace->delete();
        $this->actingAs($user)->withSession(['current_workspace_id' => $workspace->id])->get('/app/dashboard')->assertStatus(409);
        $this->assertNull(session('current_workspace_id'));
    }

    public function test_soft_deleted_workspace_candidate_without_a_selection_returns_no_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $this->membership($user, $workspace);
        $workspace->delete();
        $this->actingAs($user)->get('/app/dashboard')->assertStatus(409);
    }

    public function test_inactive_workspace_does_not_form_context(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->inactive()->create();
        $this->membership($user, $workspace);
        $this->actingAs($user)->get('/app/dashboard')->assertStatus(409);
    }

    public function test_inactive_or_suspended_users_cannot_be_added_to_a_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $action = app(ManageWorkspaceMember::class);
        foreach ([User::factory()->inactive()->create(), User::factory()->suspended()->create()] as $user) {
            try {
                $action->add($workspace, $user, WorkspaceMemberRole::Staff);
                $this->fail('Expected target user validation failure.');
            } catch (ValidationException) {
            }
        }
        $this->assertDatabaseCount('workspace_members', 0);
    }

    public function test_registration_creates_a_user_workspace_and_canonical_owner_membership_atomically(): void
    {
        $user = app(RegisterUser::class)->handle(['name' => 'New User', 'email' => 'new@example.test', 'password' => Hash::make('password')]);
        $workspace = Workspace::where('owner_id', $user->id)->sole();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseCount('workspace_members', 1);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => WorkspaceMemberRole::Owner->value, 'status' => UserStatus::Active->value]);
    }

    public function test_registration_rolls_back_all_records_when_an_enclosing_transaction_fails(): void
    {
        try {
            DB::transaction(function () {
                app(RegisterUser::class)->handle(['name' => 'Rollback User', 'email' => 'rollback@example.test', 'password' => Hash::make('password')]);
                throw new \RuntimeException('Force rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('workspace_members', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_super_admin_without_membership_redirects_to_admin_after_login_and_verification(): void
    {
        $admin = User::factory()->unverified()->create(['platform_role' => PlatformRole::SuperAdmin]);
        Volt::test('pages.auth.login')->set('form.email', $admin->email)->set('form.password', 'password')->call('login')->assertRedirect(route('admin.dashboard', absolute: false));
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinute(), ['id' => $admin->id, 'hash' => sha1($admin->email)]);
        $this->actingAs($admin)->get($url)->assertRedirect(route('admin.dashboard', absolute: false).'?verified=1');
    }

    public function test_super_admin_with_legacy_membership_remains_platform_only(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $workspace = Workspace::factory()->create();
        $this->membership($admin, $workspace, WorkspaceMemberRole::Manager);
        Volt::test('pages.auth.login')->set('form.email', $admin->email)->set('form.password', 'password')->call('login')->assertRedirect(route('admin.dashboard', absolute: false));
        $this->actingAs($admin)->withSession(['current_workspace_id' => $workspace->id])->get('/app/dashboard')->assertForbidden();
        $this->actingAs($admin)->post(route('app.workspace.switch'), ['workspace_id' => $workspace->id])->assertForbidden();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_super_admin_cannot_be_added_to_a_workspace(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->expectException(ValidationException::class);
        app(ManageWorkspaceMember::class)->add(Workspace::factory()->create(), $admin, WorkspaceMemberRole::Staff);
    }

    public function test_user_policy_limits_platform_dashboard_authorization_to_the_super_admin_themself(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $regular = User::factory()->create();
        $this->assertTrue(Gate::forUser($admin)->allows('viewPlatformDashboard', $admin));
        $this->assertFalse(Gate::forUser($regular)->allows('viewPlatformDashboard', $regular));
        $this->assertFalse(Gate::forUser($admin)->allows('viewPlatformDashboard', $regular));
        $this->actingAs($regular)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_promotion_rejects_users_with_active_or_suspended_memberships_without_changes(): void
    {
        foreach ([UserStatus::Active, UserStatus::Suspended] as $status) {
            $user = User::factory()->create();
            $membership = $this->membership($user, Workspace::factory()->create());
            $membership->update(['status' => $status]);
            try {
                app(PromoteUserToSuperAdmin::class)->handle($user);
                $this->fail('Expected promotion to be rejected.');
            } catch (ValidationException) {
            }
            $this->assertNull($user->fresh()->platform_role);
            $this->assertDatabaseHas('workspace_members', ['id' => $membership->id, 'status' => $status->value]);
        }
    }

    public function test_promotion_rejects_canonical_and_corrupt_workspace_owners_without_changes(): void
    {
        $canonicalOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $canonicalOwner->id]);
        $canonicalMembership = $this->membership($canonicalOwner, $workspace, WorkspaceMemberRole::Owner);
        $corruptOwner = User::factory()->create();
        $corruptWorkspace = Workspace::factory()->create(['owner_id' => $corruptOwner->id]);

        foreach ([$canonicalOwner, $corruptOwner] as $user) {
            try {
                app(PromoteUserToSuperAdmin::class)->handle($user);
                $this->fail('Expected promotion to be rejected.');
            } catch (ValidationException) {
            }
            $this->assertNull($user->fresh()->platform_role);
        }
        $this->assertDatabaseHas('workspace_members', ['id' => $canonicalMembership->id, 'role' => WorkspaceMemberRole::Owner->value]);
        $this->assertDatabaseHas('workspaces', ['id' => $corruptWorkspace->id, 'owner_id' => $corruptOwner->id]);
    }

    public function test_successful_promotion_rolls_back_when_an_enclosing_transaction_fails(): void
    {
        $user = User::factory()->create();
        try {
            DB::transaction(function () use ($user) {
                app(PromoteUserToSuperAdmin::class)->handle($user);
                throw new \RuntimeException('Force rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertNull($user->fresh()->platform_role);
        $this->assertDatabaseCount('workspace_members', 0);
    }

    public function test_workspace_member_policy_fails_closed_for_legacy_super_admin_memberships(): void
    {
        $workspace = Workspace::factory()->create();
        $target = $this->membership(User::factory()->create(), $workspace, WorkspaceMemberRole::Staff);
        $policy = app(WorkspaceMemberPolicy::class);

        foreach ([WorkspaceMemberRole::Owner, WorkspaceMemberRole::Manager, WorkspaceMemberRole::Staff] as $role) {
            $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
            $this->membership($admin, $workspace, $role);
            $this->assertFalse($policy->view($admin, $target));
            $this->assertFalse($policy->create($admin, $workspace));
            $this->assertFalse($policy->update($admin, $target));
            $this->assertFalse($policy->delete($admin, $target));
            $this->assertFalse($policy->changeRole($admin, $target));
            $this->assertFalse($policy->changeStatus($admin, $target));
        }
    }

    public function test_workspace_member_policy_preserves_normal_owner_manager_and_staff_outcomes(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $target = $this->membership(User::factory()->create(), $workspace, WorkspaceMemberRole::Staff);
        $this->membership($owner, $workspace, WorkspaceMemberRole::Owner);
        $this->membership($manager, $workspace, WorkspaceMemberRole::Manager);
        $this->membership($staff, $workspace, WorkspaceMemberRole::Staff);
        $policy = app(WorkspaceMemberPolicy::class);

        $this->assertTrue($policy->view($owner, $target));
        $this->assertTrue($policy->create($owner, $workspace));
        $this->assertTrue($policy->update($owner, $target));
        $this->assertTrue($policy->delete($owner, $target));
        $this->assertTrue($policy->changeRole($owner, $target));
        $this->assertTrue($policy->changeStatus($owner, $target));
        $this->assertTrue($policy->update($manager, $target));
        $this->assertTrue($policy->delete($manager, $target));
        $this->assertTrue($policy->changeRole($manager, $target));
        $this->assertTrue($policy->changeStatus($manager, $target));
        $this->assertFalse($policy->update($staff, $target));
        $this->assertFalse($policy->delete($staff, $target));
    }
}
