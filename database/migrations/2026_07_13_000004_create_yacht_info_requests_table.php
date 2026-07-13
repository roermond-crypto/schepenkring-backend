<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_info_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained('yachts')->cascadeOnDelete();
            $table->foreignId('requested_by_id')->constrained('users');
            $table->json('items');
            // open | resolved
            $table->string('status')->default('open');
            $table->foreignId('resolved_by_id')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['yacht_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_info_requests');
    }
};
