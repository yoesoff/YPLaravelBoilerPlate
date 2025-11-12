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
                $email = strtolower($role) . "{$i}@example.com";
                // database/seeders/UserSeeder.php
                if (!User::where('email', $email)->exists()) {
                    User::create([
                        'name' => 'Test ' . $role . ' User' . $i,
                        'email' => $email,
                        'password' => Hash::make('password123'),
                        'role' => $role,
                        'active' => true,
                    ]);
                }
            }
        }
    }
}
