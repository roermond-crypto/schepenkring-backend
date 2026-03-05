<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SellerRoleSeeder extends Seeder
{
    public function run()
    {
        Role::create(['name' => 'Seller', 'guard_name' => 'web']);
    }
}