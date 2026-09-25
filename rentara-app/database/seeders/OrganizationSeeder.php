<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\OrganizationRole;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $owner = User::factory()->create([
            'name' => 'Rentara Demo Owner',
            'email' => 'owner@example.test',
        ]);

        $organization = Organization::factory()->create([
            'name' => 'Kos Mentari Jakarta',
        ]);

        $organization->memberships()->create([
            'user_id' => $owner->id,
            'role' => OrganizationRole::Owner,
            'accepted_at' => now(),
        ]);
    }
}
