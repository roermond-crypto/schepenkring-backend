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
        Schema::table('yachts', function (Blueprint $table) {
            $table->string('google_offer_id')->nullable()->index();
            $table->string('google_product_id')->nullable();
            $table->enum('google_status', ['synced', 'error', 'pending', 'not_eligible'])->default('pending');
            $table->timestamp('google_last_sync_at')->nullable();
            $table->text('google_last_error')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn([
                'google_offer_id',
                'google_product_id',
                'google_status',
                'google_last_sync_at',
                'google_last_error',
            ]);
        });
    }
};
