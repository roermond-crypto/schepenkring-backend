<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // New columns
            $table->foreignUuid('conversation_id')->nullable()->constrained('conversations')->onDelete('set null');
            $table->foreignId('assigned_employee_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('source_url')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            
            // Client is renamed to converted_client for clarity, but client_id already exists.
            // Leaving client_id as is.
        });

        // Add lead_id foreign key constraint to conversations (to complete circular reference gracefully)
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropForeign(['assigned_employee_id']);
            $table->dropColumn([
                'conversation_id', 'assigned_employee_id', 'source_url', 'name', 'email', 'phone'
            ]);
        });
    }
};
