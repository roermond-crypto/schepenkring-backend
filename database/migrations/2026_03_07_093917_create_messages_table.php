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
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->enum('sender_type', ['employee', 'visitor', 'system'])->default('visitor');
            $table->foreignId('employee_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('body');
            $table->string('client_message_id')->nullable();
            $table->enum('delivery_state', ['sending', 'sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->timestamps();

            $table->unique(['conversation_id', 'client_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
