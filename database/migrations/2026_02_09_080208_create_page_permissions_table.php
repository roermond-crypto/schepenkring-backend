<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('page_key')->unique();
            $table->string('page_name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insert default pages
        $pages = [
            ['page_key' => 'tasks', 'page_name' => 'Tasks Page', 'description' => 'Access to view tasks'],
            ['page_key' => 'assign_tasks', 'page_name' => 'Assign Tasks', 'description' => 'Access to assign tasks'],
            ['page_key' => 'view_users', 'page_name' => 'View Users', 'description' => 'Access to view users'],
            ['page_key' => 'manage_yachts', 'page_name' => 'Create/Edit Yachts', 'description' => 'Access to manage yachts'],
            ['page_key' => 'biddings', 'page_name' => 'Biddings Page', 'description' => 'Access to biddings'],
            ['page_key' => 'blog', 'page_name' => 'Blog Page', 'description' => 'Access to blog management'],
        ];

        DB::table('page_permissions')->insert($pages);
    }

    public function down(): void
    {
        Schema::dropIfExists('page_permissions');
    }
};