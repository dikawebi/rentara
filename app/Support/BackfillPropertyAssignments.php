<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class BackfillPropertyAssignments
{
    /**
     * Idempotent backfill: every active manager/staff member (active user,
     * non-super-admin) is assigned to every non-trashed property of the same
     * workspace. Suspended memberships are skipped and owner rows are never
     * created. Safe to run multiple times.
     */
    public function run(): void
    {
        $now = now();

        DB::table('workspaces')->select('id')->orderBy('id')->chunkById(100, function ($workspaces) use ($now) {
            foreach ($workspaces as $workspace) {
                $propertyIds = DB::table('properties')
                    ->where('workspace_id', $workspace->id)
                    ->whereNull('deleted_at')
                    ->pluck('id');

                if ($propertyIds->isEmpty()) {
                    continue;
                }

                $memberUserIds = DB::table('workspace_members')
                    ->join('users', 'users.id', '=', 'workspace_members.user_id')
                    ->where('workspace_members.workspace_id', $workspace->id)
                    ->whereIn('workspace_members.role', ['manager', 'staff'])
                    ->where('workspace_members.status', 'active')
                    ->where('users.status', 'active')
                    ->where(function ($query) {
                        $query->whereNull('users.platform_role')
                            ->orWhere('users.platform_role', '!=', 'super_admin');
                    })
                    ->pluck('workspace_members.user_id');

                if ($memberUserIds->isEmpty()) {
                    continue;
                }

                $rows = [];
                foreach ($propertyIds as $propertyId) {
                    foreach ($memberUserIds as $userId) {
                        $rows[] = [
                            'workspace_id' => $workspace->id,
                            'property_id' => $propertyId,
                            'user_id' => $userId,
                            'created_by' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('property_assignments')->insertOrIgnore($chunk);
                }
            }
        });
    }
}
