<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add response storage columns to idempotency_keys for HTTP-level response replay.
     */
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->unsignedSmallInteger('response_code')->nullable()->after('actor_id');
            $table->longText('response_body')->nullable()->after('response_code');
            $table->timestamp('expires_at')->nullable()->after('response_body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropColumn(['response_code', 'response_body', 'expires_at']);
        });
    }
};
