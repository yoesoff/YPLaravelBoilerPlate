<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Administrator', 'Manager', 'User'];
        foreach ($roles as $role) {
            for ($i = 1; $i <= 3; $i++) {
                User::create([
                    'name' => "{$role} {$i}",
                    'email' => strtolower($role) . "{$i}@example.com",
                    'password' => Hash::make('password123'),
                    'role' => $role,
                    'active' => true,
                ]);
            }
        }
    }
}
