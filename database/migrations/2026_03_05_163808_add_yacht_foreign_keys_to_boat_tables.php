<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('boat_checklist_statuses', function (Blueprint $table) {
            $table->foreign('boat_id')->references('id')->on('yachts')->cascadeOnDelete();
        });

        Schema::table('boat_documents', function (Blueprint $table) {
            $table->foreign('boat_id')->references('id')->on('yachts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('boat_documents', function (Blueprint $table) {
            $table->dropForeign(['boat_id']);
        });

        Schema::table('boat_checklist_statuses', function (Blueprint $table) {
            $table->dropForeign(['boat_id']);
        });
    }
};
