<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add the missing page permissions
        $missingPages = [
            ['page_key' => 'dashboard', 'page_name' => 'Dashboard', 'description' => 'Access to dashboard overview'],
            ['page_key' => 'yachts', 'page_name' => 'Yachts', 'description' => 'Access to yachts management'],
            ['page_key' => 'logs', 'page_name' => 'Logs Page', 'description' => 'Access to system logs'],
            ['page_key' => 'partner_boats', 'page_name' => 'Partner Boats', 'description' => 'Access to partner boats'],
            ['page_key' => 'settings', 'page_name' => 'Settings', 'description' => 'Access to account settings'],
        ];

        foreach ($missingPages as $page) {
            // Check if the page_key already exists
            $exists = DB::table('page_permissions')
                ->where('page_key', $page['page_key'])
                ->exists();
            
            if (!$exists) {
                DB::table('page_permissions')->insert($page);
            }
        }
    }

    public function down(): void
    {
        // Optionally remove these pages on rollback
        $pageKeys = ['dashboard', 'yachts', 'logs', 'partner_boats', 'settings'];
        DB::table('page_permissions')->whereIn('page_key', $pageKeys)->delete();
    }
};