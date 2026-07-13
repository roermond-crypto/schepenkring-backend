<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->renameColumn('type', 'category');
        });

        DB::table('platforms')
            ->whereNotIn('category', ['marketplace', 'portal', 'partner', 'own_website'])
            ->update(['category' => 'marketplace']);
    }

    public function down(): void
    {
        Schema::table('platforms', function (Blueprint $table) {
            $table->renameColumn('category', 'type');
        });
    }
};
