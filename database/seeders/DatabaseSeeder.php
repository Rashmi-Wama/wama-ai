<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(BusinessDemoSeeder::class);

        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'role' => 'Super Admin',
            ],
            [
                'name' => 'HR Admin',
                'email' => 'hradmin@example.com',
                'role' => 'HR Admin',
            ],
            [
                'name' => 'HR User',
                'email' => 'hruser@example.com',
                'role' => 'HR User',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
