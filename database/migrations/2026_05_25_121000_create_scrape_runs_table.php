<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scrape_runs')) {
            return;
        }

        Schema::create('scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source')->index();
            $table->string('status')->default('running')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedInteger('boats_seen')->default(0);
            $table->unsignedInteger('boats_imported')->default(0);
            $table->unsignedInteger('boats_updated')->default(0);
            $table->unsignedInteger('boats_skipped')->default(0);
            $table->unsignedInteger('boats_invalid')->default(0);
            $table->unsignedInteger('failed_pages')->default(0);
            $table->unsignedInteger('expected_total')->nullable();
            $table->decimal('completeness_ratio', 6, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_runs');
    }
};
