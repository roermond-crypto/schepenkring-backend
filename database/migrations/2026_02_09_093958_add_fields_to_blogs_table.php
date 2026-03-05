<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->string('slug')->unique()->after('title');
            $table->string('excerpt')->nullable()->after('title');
            $table->string('featured_image')->nullable()->after('content');
            $table->string('status')->default('draft')->after('author');
            $table->integer('views')->default(0)->after('status');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            // Add indexes
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->dropColumn(['slug', 'excerpt', 'featured_image', 'status', 'views', 'user_id']);
        });
    }
};