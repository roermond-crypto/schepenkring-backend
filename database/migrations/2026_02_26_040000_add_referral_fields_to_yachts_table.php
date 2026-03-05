<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            if (!Schema::hasColumn('yachts', 'ref_code')) {
                $table->string('ref_code')->nullable()->after('advertising_channels');
            }
            if (!Schema::hasColumn('yachts', 'ref_harbor_id')) {
                $table->unsignedBigInteger('ref_harbor_id')->nullable()->after('ref_code');
                $table->foreign('ref_harbor_id')->references('id')->on('users')->nullOnDelete();
                $table->index('ref_harbor_id');
            }
            if (!Schema::hasColumn('yachts', 'utm_source')) {
                $table->string('utm_source')->nullable()->after('ref_harbor_id');
            }
            if (!Schema::hasColumn('yachts', 'utm_medium')) {
                $table->string('utm_medium')->nullable()->after('utm_source');
            }
            if (!Schema::hasColumn('yachts', 'utm_campaign')) {
                $table->string('utm_campaign')->nullable()->after('utm_medium');
            }
            if (!Schema::hasColumn('yachts', 'utm_term')) {
                $table->string('utm_term')->nullable()->after('utm_campaign');
            }
            if (!Schema::hasColumn('yachts', 'utm_content')) {
                $table->string('utm_content')->nullable()->after('utm_term');
            }
            if (!Schema::hasColumn('yachts', 'ref_captured_at')) {
                $table->timestamp('ref_captured_at')->nullable()->after('utm_content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            if (Schema::hasColumn('yachts', 'ref_harbor_id')) {
                $table->dropForeign(['ref_harbor_id']);
                $table->dropIndex(['ref_harbor_id']);
                $table->dropColumn('ref_harbor_id');
            }
            foreach (['ref_code', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref_captured_at'] as $column) {
                if (Schema::hasColumn('yachts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
