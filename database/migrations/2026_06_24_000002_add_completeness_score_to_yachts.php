<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->decimal('completeness_score', 5, 2)->nullable()->after('yachtshift_publish_status')->comment('0-100 overall completeness score');
            $table->json('completeness_breakdown')->nullable()->after('completeness_score')->comment('Per-dimension scores as JSON');
            $table->timestamp('completeness_calculated_at')->nullable()->after('completeness_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn(['completeness_score', 'completeness_breakdown', 'completeness_calculated_at']);
        });
    }
};
