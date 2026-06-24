<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yachtshift_sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained('yachts')->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('field_name', 120);
            $table->longText('local_value')->nullable();
            $table->longText('remote_value')->nullable();
            $table->string('status')->default('pending');
            $table->string('resolution')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['yacht_id', 'status']);
            $table->index(['external_id', 'field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yachtshift_sync_conflicts');
    }
};
