<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_templates', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 255)->index();
            $table->string('model', 255)->index();
            $table->integer('year_min')->nullable();
            $table->integer('year_max')->nullable();
            $table->string('match_level', 20)->default('exact'); // exact, close, model_only
            $table->unsignedInteger('version')->default(1);
            $table->json('source_boat_ids');              // array of yacht IDs used to build template
            $table->unsignedInteger('source_boat_count')->default(0);
            $table->json('fields_json')->nullable();       // detected field structure
            $table->json('known_values_json')->nullable(); // consistent values across source boats
            $table->json('required_fields_json')->nullable(); // fields present in >80% of sources
            $table->json('optional_fields_json')->nullable(); // fields present in 30-80%
            $table->json('missing_fields_json')->nullable();  // fields present in <30%
            $table->json('field_stats_json')->nullable();     // per-field fill rate + value distribution
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['brand', 'model', 'match_level'], 'bt_brand_model_match_unique');
            $table->index(['is_active', 'brand', 'model']);
        });

        Schema::table('yachts', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('boat_type_id')->constrained('boat_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
        });
        Schema::dropIfExists('boat_templates');
    }
};
