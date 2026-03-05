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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('result')->default('SUCCESS')->after('risk_level');
            $table->foreignId('location_id')->nullable()->after('impersonator_id')->constrained('locations')->nullOnDelete();
            $table->string('entity_type')->nullable()->after('target_id');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            $table->json('snapshot_before')->nullable()->after('meta');
            $table->json('snapshot_after')->nullable()->after('snapshot_before');
            $table->string('ip_hash', 64)->nullable()->after('ip_address');
            $table->string('device_id')->nullable()->after('user_agent');
            $table->string('request_id')->nullable()->after('device_id');
            $table->string('idempotency_key')->nullable()->after('request_id');

            $table->index('created_at');
            $table->index('location_id');
            $table->index('actor_id');
            $table->index('action');
            $table->index('entity_type');
            $table->index('entity_id');
            $table->index('risk_level');
            $table->index('result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['location_id']);
            $table->dropIndex(['actor_id']);
            $table->dropIndex(['action']);
            $table->dropIndex(['entity_type']);
            $table->dropIndex(['entity_id']);
            $table->dropIndex(['risk_level']);
            $table->dropIndex(['result']);

            $table->dropColumn([
                'result',
                'location_id',
                'entity_type',
                'entity_id',
                'snapshot_before',
                'snapshot_after',
                'ip_hash',
                'device_id',
                'request_id',
                'idempotency_key',
            ]);
        });
    }
};
