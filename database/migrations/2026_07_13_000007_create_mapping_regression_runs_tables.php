<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded with hasTable()/hasIndex() checks: on a server where this
        // migration already failed partway through (MySQL's CREATE TABLE
        // auto-commits, so both tables can exist even though the migration
        // itself never got recorded as run — see the identifier-length fix
        // below), re-running it must finish cleanly rather than blow up on
        // "table already exists". On a fresh install, both tables and the
        // index are created from scratch exactly as before.

        if (! Schema::hasTable('mapping_regression_runs')) {
            Schema::create('mapping_regression_runs', function (Blueprint $table) {
                $table->id();
                // The OpenMarineFieldMappingVersion.version active when this run
                // executed, nullable since no mapping version may exist yet.
                $table->unsignedBigInteger('mapping_version')->nullable();
                $table->unsignedInteger('total_yachts')->default(0);
                $table->unsignedInteger('passed_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->foreignId('triggered_by_id')->nullable()->constrained('users');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mapping_regression_run_results')) {
            Schema::create('mapping_regression_run_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mapping_regression_run_id')->constrained('mapping_regression_runs')->cascadeOnDelete();
                $table->foreignId('yacht_id')->constrained('yachts')->cascadeOnDelete();
                $table->boolean('passed');
                $table->json('errors')->nullable();
                $table->json('warnings')->nullable();
                $table->timestamps();
            });
        }

        // Explicit short name — the auto-generated one
        // ("mapping_regression_run_results_mapping_regression_run_id_passed_index")
        // is 71 characters, over MySQL's 64-character identifier limit.
        if (! Schema::hasIndex('mapping_regression_run_results', ['mapping_regression_run_id', 'passed'])) {
            Schema::table('mapping_regression_run_results', function (Blueprint $table) {
                $table->index(['mapping_regression_run_id', 'passed'], 'mrr_results_run_passed_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mapping_regression_run_results');
        Schema::dropIfExists('mapping_regression_runs');
    }
};
