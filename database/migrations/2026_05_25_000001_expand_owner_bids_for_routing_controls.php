<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_bids', function (Blueprint $table) {
            if (! Schema::hasColumn('owner_bids', 'location_id')) {
                $table->foreignId('location_id')->nullable()->after('yacht_id')->constrained('locations')->nullOnDelete();
            }

            if (! Schema::hasColumn('owner_bids', 'assigned_broker_id')) {
                $table->foreignId('assigned_broker_id')->nullable()->after('seller_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('owner_bids', 'routing_mode')) {
                $table->string('routing_mode')->default('direct')->after('type');
            }

            if (! Schema::hasColumn('owner_bids', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('owner_bids', 'ai_summary')) {
                $table->text('ai_summary')->nullable()->after('expires_at');
            }

            if (! Schema::hasColumn('owner_bids', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('ai_summary');
            }

            if (! Schema::hasColumn('owner_bids', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('admin_notes');
            }

            $table->index(['location_id', 'status'], 'owner_bids_location_status_idx');
            $table->index(['assigned_broker_id', 'status'], 'owner_bids_broker_status_idx');
            $table->index('expires_at', 'owner_bids_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('owner_bids', function (Blueprint $table) {
            $table->dropIndex('owner_bids_location_status_idx');
            $table->dropIndex('owner_bids_broker_status_idx');
            $table->dropIndex('owner_bids_expires_at_idx');

            if (Schema::hasColumn('owner_bids', 'location_id')) {
                $table->dropForeign(['location_id']);
            }

            if (Schema::hasColumn('owner_bids', 'assigned_broker_id')) {
                $table->dropForeign(['assigned_broker_id']);
            }

            $table->dropColumn([
                'location_id',
                'assigned_broker_id',
                'routing_mode',
                'expires_at',
                'ai_summary',
                'admin_notes',
                'paused_at',
            ]);
        });
    }
};
