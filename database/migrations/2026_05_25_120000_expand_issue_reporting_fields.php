<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            if (! Schema::hasColumn('issues', 'screenshot_path')) {
                $table->string('screenshot_path')->nullable();
            }

            if (! Schema::hasColumn('issues', 'screenshot_original_name')) {
                $table->string('screenshot_original_name')->nullable();
            }

            if (! Schema::hasColumn('issues', 'screenshot_status')) {
                $table->string('screenshot_status')->default('none')->index();
            }

            if (! Schema::hasColumn('issues', 'page_url')) {
                $table->text('page_url')->nullable();
            }

            if (! Schema::hasColumn('issues', 'browser')) {
                $table->string('browser')->nullable();
            }

            if (! Schema::hasColumn('issues', 'device')) {
                $table->string('device')->nullable();
            }

            if (! Schema::hasColumn('issues', 'logs')) {
                $table->json('logs')->nullable();
            }

            if (! Schema::hasColumn('issues', 'ai_status')) {
                $table->string('ai_status')->default('pending')->index();
            }

            if (! Schema::hasColumn('issues', 'ai_summary')) {
                $table->text('ai_summary')->nullable();
            }

            if (! Schema::hasColumn('issues', 'ai_priority')) {
                $table->string('ai_priority')->nullable()->index();
            }

            if (! Schema::hasColumn('issues', 'ai_suggested_fix')) {
                $table->text('ai_suggested_fix')->nullable();
            }

            if (! Schema::hasColumn('issues', 'ai_error')) {
                $table->text('ai_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            foreach ([
                'screenshot_path',
                'screenshot_original_name',
                'screenshot_status',
                'page_url',
                'browser',
                'device',
                'logs',
                'ai_status',
                'ai_summary',
                'ai_priority',
                'ai_suggested_fix',
                'ai_error',
            ] as $column) {
                if (Schema::hasColumn('issues', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
