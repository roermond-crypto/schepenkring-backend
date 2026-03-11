<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('id')->constrained('locations')->nullOnDelete();
            $table->uuid('source_message_id')->nullable()->after('embedding');
            $table->foreignId('trained_by_user_id')->nullable()->after('source_message_id')->constrained('users')->nullOnDelete();

            $table->index(['location_id', 'category']);
            $table->index('source_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropForeign(['trained_by_user_id']);
            $table->dropIndex(['location_id', 'category']);
            $table->dropIndex(['source_message_id']);
            $table->dropColumn([
                'location_id',
                'source_message_id',
                'trained_by_user_id',
            ]);
        });
    }
};
