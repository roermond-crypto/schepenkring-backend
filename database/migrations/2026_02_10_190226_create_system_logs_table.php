<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_system_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemLogsTable extends Migration
{
    public function up()
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // task_update, yacht_update, bidding_update, user_login, etc.
            $table->string('entity_type'); // Task, Yacht, Bidding, User
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->json('changes')->nullable();
            $table->text('description');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['event_type', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_logs');
    }
}