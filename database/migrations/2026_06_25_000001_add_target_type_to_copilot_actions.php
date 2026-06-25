<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copilot_actions', function (Blueprint $table) {
            $table->enum('target_type', ['page', 'modal', 'api', 'search', 'ai'])
                ->default('page')
                ->after('module');
        });
    }

    public function down(): void
    {
        Schema::table('copilot_actions', function (Blueprint $table) {
            $table->dropColumn('target_type');
        });
    }
};
