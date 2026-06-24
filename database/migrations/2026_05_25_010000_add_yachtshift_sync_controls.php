<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            if (! Schema::hasColumn('yachts', 'yachtshift_publish_status')) {
                $table->string('yachtshift_publish_status')->default('draft')->after('yachtshift_synced_at');
            }

            if (! Schema::hasColumn('yachts', 'yachtshift_last_imported_at')) {
                $table->timestamp('yachtshift_last_imported_at')->nullable()->after('yachtshift_publish_status');
            }

            if (! Schema::hasColumn('yachts', 'yachtshift_last_exported_at')) {
                $table->timestamp('yachtshift_last_exported_at')->nullable()->after('yachtshift_last_imported_at');
            }

            if (! Schema::hasColumn('yachts', 'yachtshift_last_export_error')) {
                $table->text('yachtshift_last_export_error')->nullable()->after('yachtshift_last_exported_at');
            }

            if (! Schema::hasColumn('yachts', 'yachtshift_sync_summary')) {
                $table->text('yachtshift_sync_summary')->nullable()->after('yachtshift_last_export_error');
            }

            $table->index(['yachtshift_publish_status', 'yachtshift_synced_at'], 'yachts_yachtshift_publish_status_idx');
        });

        Schema::table('boat_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('boat_fields', 'can_import')) {
                $table->boolean('can_import')->default(true)->after('is_active');
            }

            if (! Schema::hasColumn('boat_fields', 'can_export')) {
                $table->boolean('can_export')->default(true)->after('can_import');
            }

            if (! Schema::hasColumn('boat_fields', 'never_export')) {
                $table->boolean('never_export')->default(false)->after('can_export');
            }
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropIndex('yachts_yachtshift_publish_status_idx');
            $table->dropColumn([
                'yachtshift_publish_status',
                'yachtshift_last_imported_at',
                'yachtshift_last_exported_at',
                'yachtshift_last_export_error',
                'yachtshift_sync_summary',
            ]);
        });

        Schema::table('boat_fields', function (Blueprint $table) {
            $table->dropColumn([
                'can_import',
                'can_export',
                'never_export',
            ]);
        });
    }
};
