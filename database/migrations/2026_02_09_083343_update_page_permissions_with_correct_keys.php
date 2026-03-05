<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if table exists and is empty, then insert
        if (DB::table('page_permissions')->count() === 0) {
            $pages = [
                ['page_key' => 'tasks', 'page_name' => 'Tasks Page', 'description' => 'Access to view tasks'],
                ['page_key' => 'assign_tasks', 'page_name' => 'Assign Tasks', 'description' => 'Access to assign tasks'],
                ['page_key' => 'view_users', 'page_name' => 'View Users', 'description' => 'Access to view users'],
                ['page_key' => 'manage_yachts', 'page_name' => 'Manage Yachts', 'description' => 'Access to manage yachts'],
                ['page_key' => 'biddings', 'page_name' => 'Biddings Page', 'description' => 'Access to biddings'],
                ['page_key' => 'blog', 'page_name' => 'Blog Page', 'description' => 'Access to blog management'],
                ['page_key' => 'dashboard', 'page_name' => 'Dashboard', 'description' => 'Access to dashboard'],
                // ['page_key' => 'settings', 'page_name' => 'Settings', 'description' => 'Access to settings'],
            ];

            DB::table('page_permissions')->insert($pages);
        }
    }

    public function down(): void
    {
        // We can delete the inserted records on rollback
        DB::table('page_permissions')->whereIn('page_key', [
            'tasks', 'assign_tasks', 'view_users', 'manage_yachts', 
            'biddings', 'blog', 'dashboard'
        ])->delete();
    }
};