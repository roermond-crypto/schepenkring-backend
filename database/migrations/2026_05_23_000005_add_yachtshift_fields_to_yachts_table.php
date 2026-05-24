<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            if (!Schema::hasColumn('yachts', 'yachtshift_id')) {
                $table->string('yachtshift_id')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('yachts', 'yachtshift_synced_at')) {
                $table->timestamp('yachtshift_synced_at')->nullable()->after('yachtshift_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $columns = array_filter(
                ['yachtshift_id', 'yachtshift_synced_at'],
                fn($col) => Schema::hasColumn('yachts', $col)
            );
            if ($columns) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
