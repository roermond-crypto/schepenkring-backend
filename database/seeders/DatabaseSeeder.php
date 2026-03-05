<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Yacht;
use App\Models\Bid;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Dynamic Permissions (idempotent)
        $permissions = [
            'manage yachts',
            'view yachts',
            'place bids',
            'accept bids',
            'manage tasks',
            'manage users'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        // 2. Create Roles and Assign Permissions (safe)
        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin']);
        $superAdmin->syncPermissions(Permission::all());

        $employee = Role::firstOrCreate(['name' => 'Employee']);
        $employee->syncPermissions(['view yachts', 'manage tasks', 'accept bids']);

        $customer = Role::firstOrCreate(['name' => 'Customer']);
        $customer->syncPermissions(['view yachts', 'place bids']);

        // 3. Create Sample Users (idempotent)
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@maritime.com'],
            [
                'name' => 'Main Admin',
                'password' => Hash::make('password123'),
                'role' => 'Admin', // legacy field
                'status' => 'Active',
                'access_level' => 'Full',
                'email_verified_at' => now(),
            ]
        );
        $adminUser->syncRoles($superAdmin);

        $staffUser = User::firstOrCreate(
            ['email' => 'staff@maritime.com'],
            [
                'name' => 'John Deckhand',
                'password' => Hash::make('password123'),
                'role' => 'Employee',
                'status' => 'Active',
                'access_level' => 'Limited'
            ]
        );
        $staffUser->syncRoles($employee);

        $clientUser = User::firstOrCreate(
            ['email' => 'client@maritime.com'],
            [
                'name' => 'Vince Millionaire',
                'password' => Hash::make('password123'),
                'role' => 'Customer',
                'status' => 'Active',
                'access_level' => 'None'
            ]
        );
        $clientUser->syncRoles($customer);

        // 4. Create a Sample Yacht & Bid safely

    }
}
