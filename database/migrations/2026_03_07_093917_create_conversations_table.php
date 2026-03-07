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
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->string('channel')->default('web_widget');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->foreignId('assigned_employee_id')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedBigInteger('lead_id')->nullable(); // Foreign key added later to avoid circular dependency
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
