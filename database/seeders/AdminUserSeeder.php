<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'customer_name' => 'Admin',
                'password' => 'password123',
                'role' => User::ROLE_ADMIN,
            ]
        );
    }
}
