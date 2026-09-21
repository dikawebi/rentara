<?php

namespace Database\Seeders;

use App\Actions\ManageWorkspaceMember;
use App\Enums\PlatformRole;
use App\Enums\UserStatus;
use App\Enums\WorkspaceMemberRole;
use App\Enums\WorkspaceStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RentaraDemoSeeder extends Seeder
{
    public const SUPER_ADMIN_EMAIL = 'admin@rentara.test';

    public const OWNER_EMAIL = 'owner@rentara.test';

    public const MANAGER_EMAIL = 'manager@rentara.test';

    public const STAFF_EMAIL = 'staff@rentara.test';

    public const WORKSPACE_SLUG = 'rentara-demo-property';

    public const WORKSPACE_NAME = 'Rentara Demo Property';

    /**
     * Seed local-only demo accounts. Safe to re-run.
     */
    public function run(): void
    {
        // Defense-in-depth: never seed demo accounts in production,
        // even if invoked directly. DatabaseSeeder already excludes
        // this seeder in production.
        if (app()->environment('production')) {
            return;
        }

        // Local-only default matches factories ('password'). Override via DEMO_PASSWORD env.
        // Never hardcode production secrets here.
        $password = (string) (env('DEMO_PASSWORD', 'password') ?: 'password');

        DB::transaction(function () use ($password): void {
            $admin = $this->demoUser(
                self::SUPER_ADMIN_EMAIL,
                'Rentara Super Admin',
                $password,
                PlatformRole::SuperAdmin,
            );

            $owner = $this->demoUser(self::OWNER_EMAIL, 'Rentara Owner', $password);
            $manager = $this->demoUser(self::MANAGER_EMAIL, 'Rentara Manager', $password);
            $staff = $this->demoUser(self::STAFF_EMAIL, 'Rentara Staff', $password);

            $workspace = Workspace::firstOrCreate(
                ['slug' => self::WORKSPACE_SLUG],
                [
                    'owner_id' => $owner->id,
                    'name' => self::WORKSPACE_NAME,
                    'status' => WorkspaceStatus::Active,
                    'timezone' => 'Asia/Jakarta',
                    'currency' => 'IDR',
                ]
            );

            // Self-heal canonical demo workspace ownership without touching other workspaces.
            if ($workspace->owner_id !== $owner->id) {
                $workspace->update(['owner_id' => $owner->id]);
            }

            // Canonical owner membership cannot go through ManageWorkspaceMember::add()
            // (Owner memberships are rejected there by design), so create it directly.
            $ownerMembership = WorkspaceMember::firstOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $owner->id],
                ['role' => WorkspaceMemberRole::Owner, 'status' => UserStatus::Active, 'joined_at' => now()]
            );

            if ($ownerMembership->role !== WorkspaceMemberRole::Owner || $ownerMembership->status !== UserStatus::Active) {
                $ownerMembership->update(['role' => WorkspaceMemberRole::Owner, 'status' => UserStatus::Active]);
            }

            // Staff-type memberships go through the existing domain action where practical.
            // Skip the action when the membership already exists to keep re-runs idempotent.
            $this->ensureMembership($workspace, $manager, WorkspaceMemberRole::Manager);
            $this->ensureMembership($workspace, $staff, WorkspaceMemberRole::Staff);

            // Platform boundary: the super_admin must never hold a workspace membership.
            WorkspaceMember::where('user_id', $admin->id)->delete();

            // NOTE: tenant@rentara.test from the playbook is intentionally excluded.
            // The tenant role/domain is deferred per Owner decision. Do NOT create a
            // tenant demo user, Property/Unit records, or invitations in this seeder.
        });
    }

    private function demoUser(string $email, string $name, string $password, ?PlatformRole $platformRole = null): User
    {
        return User::updateOrCreate(
            ['email' => mb_strtolower(trim($email))],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
                'platform_role' => $platformRole,
            ]
        );
    }

    private function ensureMembership(Workspace $workspace, User $user, WorkspaceMemberRole $role): WorkspaceMember
    {
        $existing = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->role !== $role || $existing->status !== UserStatus::Active) {
                $existing->update(['role' => $role, 'status' => UserStatus::Active]);
            }

            return $existing->fresh();
        }

        return app(ManageWorkspaceMember::class)->add($workspace, $user->fresh(), $role);
    }
}
