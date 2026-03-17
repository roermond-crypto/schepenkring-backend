<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('boat_field_value_observations');

        Schema::create('boat_field_value_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('boat_fields')->cascadeOnDelete();
            $table->string('source', 30);
            $table->string('external_key', 120)->nullable();
            $table->string('external_value', 191);
            $table->unsignedInteger('observed_count')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['field_id', 'source', 'external_value'],
                'bf_value_obs_field_source_value_uniq',
            );
            $table->index(['source', 'observed_count']);
            $table->index(['last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_field_value_observations');
    }
};
