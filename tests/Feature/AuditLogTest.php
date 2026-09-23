<?php

namespace Tests\Feature;

use App\Actions\ManageWorkspaceMember;
use App\Actions\RegisterUser;
use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\AuditLogger;
use App\Support\AuditRequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_and_workspace_creation_are_audited_in_the_same_transaction(): void
    {
        $context = AuditRequestContext::fromRequest(Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'RentaraTest/1.0']));
        $user = app(RegisterUser::class)->handle(['name' => 'Audited User', 'email' => 'audited@example.test', 'password' => Hash::make('password')], context: $context);
        $workspace = Workspace::where('owner_id', $user->id)->sole();

        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogger::REGISTRATION, 'user_id' => $user->id, 'auditable_type' => User::class, 'auditable_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogger::WORKSPACE_CREATED, 'user_id' => $user->id, 'workspace_id' => $workspace->id, 'auditable_type' => Workspace::class, 'auditable_id' => $workspace->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogger::REGISTRATION, 'ip_address' => '127.0.0.1', 'user_agent' => 'sha256:'.hash('sha256', 'RentaraTest/1.0')]);

        try {
            DB::transaction(function (): void {
                app(RegisterUser::class)->handle(['name' => 'Rollback', 'email' => 'rollback-audit@example.test', 'password' => Hash::make('password')]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_login_and_all_denied_attempts_are_audited_without_secrets_or_account_state_enumeration(): void
    {
        $user = User::factory()->create();
        Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login');

        $login = AuditLog::where('event', AuditLogger::LOGIN_SUCCEEDED)->sole();
        $this->assertSame($user->id, $login->user_id);
        $this->assertNotNull($login->ip_address);
        $this->assertSame(['last_login_at'], array_keys($login->new_values));
        $this->assertStringNotContainsString('password', json_encode($login->new_values));
        $this->assertStringNotContainsString('token', json_encode($login->new_values));

        $inactive = User::factory()->inactive()->create();
        Volt::test('pages.auth.login')->set('form.email', $inactive->email)->set('form.password', 'reset-token-password')->call('login')->assertHasErrors();
        Volt::test('pages.auth.login')->set('form.email', 'unknown@example.test')->set('form.password', 'reset-token-password')->call('login')->assertHasErrors();
        $denied = AuditLog::where('event', AuditLogger::LOGIN_DENIED)->get();
        $this->assertCount(2, $denied);
        $this->assertTrue($denied->every(fn (AuditLog $log) => $log->user_id === null && $log->new_values === null));
        $this->assertStringNotContainsString('reset-token-password', json_encode($denied->toArray()));
    }

    public function test_logout_and_platform_dashboard_access_are_logged_once_per_action(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Volt::test('layout.navigation')->call('logout');
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogger::LOGOUT, 'user_id' => $user->id]);

        $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogger::PLATFORM_DASHBOARD_ACCESSED, 'user_id' => $admin->id]);
    }

    public function test_member_lifecycle_changes_are_audited_with_safe_old_and_new_values(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceMemberRole::Owner]);
        $target = User::factory()->create();
        $members = app(ManageWorkspaceMember::class);

        $context = AuditRequestContext::fromRequest(Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'MemberContext/1.0']));
        $member = $members->add($workspace, $target, WorkspaceMemberRole::Staff, $owner, $context);
        $members->changeRole($member, WorkspaceMemberRole::Manager, $owner, $context);
        $members->suspend($member, $owner, $context);
        $members->remove($member, $owner, $context);

        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogger::MEMBER_ADDED, 'user_id' => $owner->id, 'workspace_id' => $workspace->id, 'auditable_id' => $member->id]);
        $role = AuditLog::where('event', AuditLogger::MEMBER_ROLE_CHANGED)->sole();
        $status = AuditLog::where('event', AuditLogger::MEMBER_STATUS_CHANGED)->sole();
        $removed = AuditLog::where('event', AuditLogger::MEMBER_REMOVED)->sole();
        $this->assertSame(['role' => WorkspaceMemberRole::Staff->value], $role->old_values);
        $this->assertSame(['role' => WorkspaceMemberRole::Manager->value], $role->new_values);
        $this->assertSame(['status' => UserStatus::Active->value], $status->old_values);
        $this->assertSame(['status' => UserStatus::Suspended->value], $status->new_values);
        $this->assertSame($target->id, $removed->old_values['user_id']);
        $this->assertSame('127.0.0.1', $role->ip_address);
        $this->assertSame('sha256:'.hash('sha256', 'MemberContext/1.0'), $role->user_agent);
    }

    public function test_logger_rejects_secret_and_raw_document_fields(): void
    {
        $logger = app(AuditLogger::class);
        foreach (['password' => 'secret', 'password_confirmation' => 'secret', 'reset_token' => 'secret', 'remember_token' => 'secret', 'api_key' => 'secret', 'credentials' => 'secret', 'document_path' => '/private/document', 'role' => 'Bearer secret-token', 'status' => 'password=secret'] as $key => $value) {
            try {
                $logger->assertSafeValues([$key => $value]);
                $this->fail("{$key} was accepted for audit logging.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_logger_rejects_non_positive_invoice_identifiers(): void
    {
        $logger = app(AuditLogger::class);

        foreach ([0, -1, '1', 1.5, null] as $value) {
            try {
                $logger->assertSafeValues(['invoice_id' => $value]);
                $this->fail('Invalid invoice_id was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_logger_accepts_slash_invoice_numbers_but_rejects_unsafe_formats(): void
    {
        $logger = app(AuditLogger::class);
        $logger->assertSafeValues(['invoice_number' => 'INV/2026']);

        foreach (['INV 2026', '/INV-2026', 'INV-2026/'] as $value) {
            try {
                $logger->assertSafeValues(['invoice_number' => $value]);
                $this->fail("{$value} was accepted.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_request_context_normalizes_and_discards_unsafe_user_agents(): void
    {
        $longAgent = str_repeat('A', 600)."\x00\n";
        $safe = AuditRequestContext::fromRequest(Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => $longAgent]));
        $unsafe = AuditRequestContext::fromRequest(Request::create('/', 'GET', server: ['REMOTE_ADDR' => 'not-an-ip', 'HTTP_USER_AGENT' => 'Mozilla password=secret']));

        $this->assertSame(71, strlen($safe->userAgent));
        $this->assertStringStartsWith('sha256:', $safe->userAgent);
        $this->assertSame('127.0.0.1', $safe->ipAddress);
        $this->assertNull($unsafe->userAgent);
        $this->assertNull($unsafe->ipAddress);
    }

    public function test_failed_audit_writes_roll_back_each_member_lifecycle_mutation(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceMemberRole::Owner]);
        $member = WorkspaceMember::factory()->create(['workspace_id' => $workspace->id, 'role' => WorkspaceMemberRole::Staff]);
        app()->instance(AuditLogger::class, new class extends AuditLogger {
            public function memberAdded(?User $actor, WorkspaceMember $member, ?AuditRequestContext $context = null): void { throw new \RuntimeException('audit failed'); }
            public function memberRoleChanged(?User $actor, WorkspaceMember $member, WorkspaceMemberRole $oldRole, ?AuditRequestContext $context = null): void { throw new \RuntimeException('audit failed'); }
            public function memberStatusChanged(?User $actor, WorkspaceMember $member, UserStatus $oldStatus, ?AuditRequestContext $context = null): void { throw new \RuntimeException('audit failed'); }
            public function memberRemoved(?User $actor, WorkspaceMember $member, ?AuditRequestContext $context = null): void { throw new \RuntimeException('audit failed'); }
        });
        $action = app(ManageWorkspaceMember::class);

        foreach ([
            fn () => $action->add($workspace, User::factory()->create(), WorkspaceMemberRole::Staff, $owner),
            fn () => $action->changeRole($member, WorkspaceMemberRole::Manager, $owner),
            fn () => $action->suspend($member, $owner),
            fn () => $action->remove($member, $owner),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Expected audit failure.');
            } catch (\RuntimeException) {
            }
        }

        $this->assertSame(WorkspaceMemberRole::Staff, $member->fresh()->role);
        $this->assertSame(UserStatus::Active, $member->fresh()->status);
        $this->assertDatabaseHas('workspace_members', ['id' => $member->id]);
        $this->assertDatabaseCount('workspace_members', 2);
    }

    public function test_audit_logs_cannot_be_mass_assigned_outside_the_logger(): void
    {
        $this->expectException(MassAssignmentException::class);

        AuditLog::create(['event' => AuditLogger::LOGIN_SUCCEEDED, 'new_values' => ['role' => 'Bearer secret']]);
    }

    public function test_database_rejects_raw_audit_update_and_delete(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        app(AuditLogger::class)->workspaceCreated($user, $workspace);
        $audit = AuditLog::latest('id')->firstOrFail();

        foreach ([['user_id' => null], ['workspace_id' => null], ['event' => AuditLogger::LOGOUT]] as $mutation) {
            try {
                DB::table('audit_logs')->where('id', $audit->id)->update($mutation);
                $this->fail('Raw audit update unexpectedly succeeded.');
            } catch (\Illuminate\Database\QueryException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            DB::table('audit_logs')->where('id', $audit->id)->delete();
            $this->fail('Raw audit delete unexpectedly succeeded.');
        } catch (\Illuminate\Database\QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseHas('audit_logs', ['id' => $audit->id]);
    }

    public function test_parent_deletion_preserves_historical_audit_ids(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        app(AuditLogger::class)->workspaceCreated($user, $workspace);
        $audit = AuditLog::where('workspace_id', $workspace->id)->latest('id')->firstOrFail();

        DB::table('workspaces')->where('id', $workspace->id)->delete();
        DB::table('users')->where('id', $user->id)->delete();

        $this->assertDatabaseHas('audit_logs', [
            'id' => $audit->id,
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_audit_parent_migration_removes_only_fks_and_preserves_rows_and_indexes(): void
    {
        $migration = require database_path('migrations/2026_09_23_000021_detach_audit_log_parents.php');
        $migration->up();

        $this->assertSame([], DB::select('PRAGMA foreign_key_list(audit_logs)'));
        DB::table('audit_logs')->insert(['event' => AuditLogger::LOGIN_DENIED, 'created_at' => now()]);
        $this->assertDatabaseHas('audit_logs', ['id' => 1]);
        $this->assertNotEmpty(DB::select("PRAGMA index_list('audit_logs')"));
        $migration->down();
        $this->assertCount(2, DB::select('PRAGMA foreign_key_list(audit_logs)'));
        $this->assertDatabaseHas('audit_logs', ['id' => 1]);
        $migration->up();
    }

    public function test_audit_protection_installs_both_sqlite_triggers_and_keeps_insert_path_open(): void
    {
        $triggers = collect(DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND name LIKE 'audit_logs_no_%'"));

        $this->assertSame(['audit_logs_no_delete', 'audit_logs_no_update'], $triggers->pluck('name')->sort()->values()->all());
        $this->assertTrue($triggers->firstWhere('name', 'audit_logs_no_update')->sql !== null);
        $this->assertTrue($triggers->firstWhere('name', 'audit_logs_no_delete')->sql !== null);

        $migration = require database_path('migrations/2026_09_23_000020_protect_audit_logs.php');
        $this->assertTrue($migration::supportsMysqlTriggers('mysql'));
        $this->assertTrue($migration::supportsMysqlTriggers('mariadb'));
        $this->assertFalse($migration::supportsMysqlTriggers('pgsql'));

        app(AuditLogger::class)->loginDenied(AuditRequestContext::fromRequest(Request::create('/')));
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogger::LOGIN_DENIED]);
    }

    public function test_audit_logs_ignore_fill_and_reject_force_fill_persistence(): void
    {
        $filled = new AuditLog;
        try {
            $filled->fill(['event' => AuditLogger::LOGIN_DENIED, 'user_agent' => 'attacker-token']);
            $this->fail('Filled audit log unexpectedly accepted attributes.');
        } catch (MassAssignmentException) {
        }

        $forced = new AuditLog;
        try {
            $forced->forceFill(['event' => AuditLogger::LOGIN_DENIED, 'user_agent' => 'Mozilla password=secret', 'new_values' => ['role' => 'Bearer secret']]);
            $forced->save();
            $this->fail('Force-filled audit log unexpectedly persisted.');
        } catch (InvalidArgumentException) {
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_forged_fingerprint_cannot_persist_through_eloquent(): void
    {
        $forged = 'sha256:'.str_repeat('a', 64);

        try {
            AuditLog::create(['event' => AuditLogger::LOGIN_DENIED, 'user_agent' => $forged]);
            $this->fail('Forged fingerprint unexpectedly persisted via create.');
        } catch (MassAssignmentException|InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            (new AuditLog)->fill(['event' => AuditLogger::LOGIN_DENIED, 'user_agent' => $forged]);
            $this->fail('Forged fingerprint unexpectedly accepted via fill.');
        } catch (MassAssignmentException|InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            (new AuditLog)->forceFill(['event' => AuditLogger::LOGIN_DENIED, 'user_agent' => $forged]);
            $this->fail('Forged fingerprint unexpectedly accepted via forceFill.');
        } catch (MassAssignmentException|InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            $model = new AuditLog;
            $model->forceFill(['event' => AuditLogger::LOGIN_DENIED, 'user_agent' => $forged]);
            $model->save();
            $this->fail('Forged fingerprint unexpectedly persisted via forceFill+save.');
        } catch (MassAssignmentException|InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            $model = new AuditLog;
            $model->setRawAttributes(['event' => AuditLogger::LOGIN_DENIED, 'user_agent' => $forged]);
            $model->save();
            $this->fail('Forged fingerprint unexpectedly persisted via raw attributes.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $existing = AuditLog::factory()->create();
        $originalAgent = $existing->user_agent;
        try {
            $existing->forceFill(['user_agent' => $forged]);
            $this->fail('Forged fingerprint unexpectedly accepted via forceFill on update.');
        } catch (MassAssignmentException|InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            $existing->setRawAttributes(array_merge($existing->getAttributes(), ['user_agent' => $forged]));
            $existing->save();
            $this->fail('Forged fingerprint unexpectedly persisted via raw attributes on update.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($originalAgent, $existing->fresh()->user_agent);
        $this->assertDatabaseMissing('audit_logs', ['user_agent' => $forged, 'id' => $existing->id]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_factory_and_audit_logger_persist_only_safe_audit_records(): void
    {
        $factoryLog = AuditLog::factory()->create();
        $this->assertNull($factoryLog->user_agent);
        $this->assertNull($factoryLog->new_values);

        $attackerAgent = 'Mozilla/5.0 Authorization: Bearer raw-attacker-token';
        $context = AuditRequestContext::fromRequest(Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => $attackerAgent]));
        app(AuditLogger::class)->loginDenied($context);
        $loggerLog = AuditLog::where('event', AuditLogger::LOGIN_DENIED)->latest('id')->firstOrFail();

        $this->assertNull($loggerLog->user_agent);
        $this->assertStringNotContainsString('raw-attacker-token', json_encode($loggerLog->toArray()));
    }

    public function test_suspended_and_unknown_login_denials_have_equivalent_audit_records(): void
    {
        $suspended = User::factory()->suspended()->create();
        Volt::test('pages.auth.login')->set('form.email', $suspended->email)->set('form.password', 'password')->call('login')->assertHasErrors();
        Volt::test('pages.auth.login')->set('form.email', 'missing@example.test')->set('form.password', 'password')->call('login')->assertHasErrors();

        $records = AuditLog::where('event', AuditLogger::LOGIN_DENIED)->orderBy('id')->get();
        $this->assertCount(2, $records);
        $this->assertSame(
            $records->first()->only(['user_id', 'workspace_id', 'auditable_type', 'auditable_id', 'old_values', 'new_values']),
            $records->last()->only(['user_id', 'workspace_id', 'auditable_type', 'auditable_id', 'old_values', 'new_values']),
        );
    }

    public function test_denied_login_audit_rows_are_bounded_by_ip_across_varied_emails(): void
    {
        RateLimiter::clear('audit:denied-login:127.0.0.1');

        for ($attempt = 0; $attempt < 25; $attempt++) {
            Volt::test('pages.auth.login')
                ->set('form.email', "variation-{$attempt}@example.test")
                ->set('form.password', 'wrong-password')
                ->call('login')
                ->assertHasErrors();
        }

        $this->assertDatabaseCount('audit_logs', 20);
        $this->assertSame(20, AuditLog::where('event', AuditLogger::LOGIN_DENIED)->count());
    }

    public function test_registration_and_workspace_creation_roll_back_when_an_audit_write_fails(): void
    {
        $logger = new class extends AuditLogger {
            public function workspaceCreated(User $user, Workspace $workspace, ?AuditRequestContext $context = null): void
            {
                throw new \RuntimeException('audit failed');
            }
        };

        try {
            app(RegisterUser::class)->handle(['name' => 'Rollback', 'email' => 'audit-failure@example.test', 'password' => Hash::make('password')], $logger);
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException) {
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('workspace_members', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
