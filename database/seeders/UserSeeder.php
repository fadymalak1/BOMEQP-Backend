<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create ACC Admin users
        $accAdmins = [
            [
                'name' => 'ACC Admin 1',
                'email' => 'accadmin1@example.com',
                'password' => Hash::make('password123'),
                'role' => 'acc_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'ACC Admin 2',
                'email' => 'accadmin2@example.com',
                'password' => Hash::make('password123'),
                'role' => 'acc_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        ];

        // Create Training Center Admin users
        $trainingCenterAdmins = [
            [
                'name' => 'Training Center Admin 1',
                'email' => 'tcadmin1@example.com',
                'password' => Hash::make('password123'),
                'role' => 'training_center_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Training Center Admin 2',
                'email' => 'tcadmin2@example.com',
                'password' => Hash::make('password123'),
                'role' => 'training_center_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Training Center Admin 3',
                'email' => 'tcadmin3@example.com',
                'password' => Hash::make('password123'),
                'role' => 'training_center_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        ];

        // Create Instructor users
        $instructors = [
            [
                'name' => 'John Instructor',
                'email' => 'instructor1@example.com',
                'password' => Hash::make('password123'),
                'role' => 'instructor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Sarah Instructor',
                'email' => 'instructor2@example.com',
                'password' => Hash::make('password123'),
                'role' => 'instructor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Ahmed Instructor',
                'email' => 'instructor3@example.com',
                'password' => Hash::make('password123'),
                'role' => 'instructor',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        ];

        foreach (array_merge($accAdmins, $trainingCenterAdmins, $instructors) as $user) {
            if (!User::where('email', $user['email'])->exists()) {
                User::create($user);
            }
        }
    }
}

