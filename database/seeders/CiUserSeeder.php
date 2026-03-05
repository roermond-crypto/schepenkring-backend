<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CiUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'ci@example.com'],
            [
                'name' => 'CI User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => 'active',
                'otp_enabled' => false,
            ]
        );
    }
}
