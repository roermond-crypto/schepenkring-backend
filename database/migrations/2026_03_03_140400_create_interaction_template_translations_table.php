<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interaction_template_translations')) {
            $this->ensureConstraintsAndIndexes();
            return;
        }

        Schema::create('interaction_template_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interaction_template_id')
                ->constrained('interaction_templates', 'id', 'itt_template_fk')
                ->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->string('status', 20)->default('AI_DRAFT');
            $table->string('source_hash', 64)->nullable();
            $table->string('translated_from_hash', 64)->nullable();
            $table->boolean('is_legal')->default(false);
            $table->timestamps();

            $table->unique(['interaction_template_id', 'locale'], 'itt_template_locale_unique');
            $table->index(['locale', 'status'], 'itt_locale_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_template_translations');
    }

    private function ensureConstraintsAndIndexes(): void
    {
        if (!Schema::hasColumn('interaction_template_translations', 'interaction_template_id')) {
            return;
        }

        $schema = DB::getDatabaseName();

        $fkOnColumn = DB::selectOne(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1",
            [$schema, 'interaction_template_translations', 'interaction_template_id']
        );

        if (!$fkOnColumn) {
            Schema::table('interaction_template_translations', function (Blueprint $table) {
                $table->foreign('interaction_template_id', 'itt_template_fk')
                    ->references('id')
                    ->on('interaction_templates')
                    ->cascadeOnDelete();
            });
        }

        $uniqueIndexes = DB::select(
            "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND NON_UNIQUE = 0
             GROUP BY INDEX_NAME",
            [$schema, 'interaction_template_translations']
        );

        $hasTemplateLocaleUnique = false;
        foreach ($uniqueIndexes as $idx) {
            if (($idx->cols ?? '') === 'interaction_template_id,locale') {
                $hasTemplateLocaleUnique = true;
                break;
            }
        }

        if (!$hasTemplateLocaleUnique) {
            Schema::table('interaction_template_translations', function (Blueprint $table) {
                $table->unique(['interaction_template_id', 'locale'], 'itt_template_locale_unique');
            });
        }

        $normalIndexes = DB::select(
            "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND NON_UNIQUE = 1
             GROUP BY INDEX_NAME",
            [$schema, 'interaction_template_translations']
        );

        $hasLocaleStatusIdx = false;
        foreach ($normalIndexes as $idx) {
            if (($idx->cols ?? '') === 'locale,status') {
                $hasLocaleStatusIdx = true;
                break;
            }
        }

        if (!$hasLocaleStatusIdx) {
            Schema::table('interaction_template_translations', function (Blueprint $table) {
                $table->index(['locale', 'status'], 'itt_locale_status_idx');
            });
        }
    }
};
