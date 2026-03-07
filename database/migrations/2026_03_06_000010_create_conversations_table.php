<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('channel')->default('web_widget');
            $table->string('status')->default('open');
            $table->foreignId('assigned_employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'status']);
            $table->index('assigned_employee_id');
            $table->index('lead_id');
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
