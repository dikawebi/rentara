<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Test/demo data is development-only. Excluded in production, safe to run in testing.
        if (! app()->environment('production')) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            $this->call(RentaraDemoSeeder::class);
        }
    }
}
