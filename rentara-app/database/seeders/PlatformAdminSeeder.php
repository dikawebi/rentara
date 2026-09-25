<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $email = env('LOCAL_PLATFORM_ADMIN_EMAIL');
        $password = env('LOCAL_PLATFORM_ADMIN_PASSWORD');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Rentara Platform Admin',
                'password' => $password,
            ],
        );

        $admin->forceFill([
            'email_verified_at' => now(),
            'is_platform_admin' => true,
        ])->save();
    }
}
