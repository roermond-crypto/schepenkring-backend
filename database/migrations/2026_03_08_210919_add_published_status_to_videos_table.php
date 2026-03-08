<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum column to include 'published'
        DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('queued', 'processing', 'ready', 'failed', 'published') DEFAULT 'queued'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('queued', 'processing', 'ready', 'failed') DEFAULT 'queued'");
    }
};
