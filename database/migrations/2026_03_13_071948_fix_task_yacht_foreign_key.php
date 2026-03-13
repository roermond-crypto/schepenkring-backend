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
        Schema::table('tasks', function (Blueprint $table) {
            // Drop the old incorrect foreign key
            // Note: In Laravel/MySQL, the index name is typically sourceTable_columnName_foreign
            try {
                $table->dropForeign(['yacht_id']);
            } catch (\Exception $e) {
                // Ignore if it doesn't exist or has a different name
            }

            // Create the new correct foreign key pointing to 'yachts' table
            $table->foreign('yacht_id')
                ->references('id')
                ->on('yachts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            try {
                $table->dropForeign(['yacht_id']);
            } catch (\Exception $e) {
            }

            $table->foreign('yacht_id')
                ->references('id')
                ->on('boats')
                ->onDelete('set null');
        });
    }
};
