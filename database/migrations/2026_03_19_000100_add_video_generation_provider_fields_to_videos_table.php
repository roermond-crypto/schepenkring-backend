<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            if (! Schema::hasColumn('videos', 'generation_provider')) {
                $table->string('generation_provider')->nullable()->after('generation_trigger');
            }

            if (! Schema::hasColumn('videos', 'provider_job_id')) {
                $table->string('provider_job_id')->nullable()->after('generation_provider');
            }

            if (! Schema::hasColumn('videos', 'provider_status')) {
                $table->string('provider_status')->nullable()->after('provider_job_id');
            }

            if (! Schema::hasColumn('videos', 'provider_progress')) {
                $table->unsignedTinyInteger('provider_progress')->nullable()->after('provider_status');
            }

            if (! Schema::hasColumn('videos', 'provider_payload')) {
                $table->json('provider_payload')->nullable()->after('provider_progress');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $columns = [
                'generation_provider',
                'provider_job_id',
                'provider_status',
                'provider_progress',
                'provider_payload',
            ];

            $existing = array_values(array_filter(
                $columns,
                static fn (string $column) => Schema::hasColumn('videos', $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
