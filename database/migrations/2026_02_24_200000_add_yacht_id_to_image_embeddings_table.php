<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_embeddings', function (Blueprint $table) {
            $table->unsignedBigInteger('yacht_id')->nullable()->after('id');
            $table->boolean('is_main_image')->default(false)->after('yacht_id');
            $table->foreign('yacht_id')->references('id')->on('yachts')->onDelete('set null');
            $table->index('yacht_id');
        });
    }

    public function down(): void
    {
        Schema::table('image_embeddings', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
            $table->dropIndex(['yacht_id']);
            $table->dropColumn(['yacht_id', 'is_main_image']);
        });
    }
};
