<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        User::query()->updateOrCreate(
            ['email' => 'admin@glowingdark.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $this->command?->info('Super Admin ready:');
        $this->command?->info('  Email:    admin@glowingdark.com');
        $this->command?->info('  Password: password');
    }
}
