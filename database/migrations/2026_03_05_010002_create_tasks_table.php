<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('Medium');
            $table->string('status')->default('New');
            $table->string('assignment_status')->default('accepted');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('yacht_id')->nullable()->constrained('boats')->nullOnDelete();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('reminder_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->string('type')->default('assigned');
            $table->foreignId('column_id')->nullable()->constrained('columns')->nullOnDelete();
            $table->integer('position')->default(0);
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('client_visible')->default(false);
            $table->timestamps();

            $table->index(['location_id', 'status']);
            $table->index(['assigned_to', 'user_id', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
