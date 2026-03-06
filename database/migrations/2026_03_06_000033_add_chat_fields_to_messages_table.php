<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('text')->nullable()->after('sender_type');
            $table->string('language', 5)->nullable()->after('text');
            $table->string('channel')->default('web')->after('language');
            $table->string('external_message_id')->nullable()->after('channel');
            $table->string('message_type')->default('text')->after('external_message_id');
            $table->string('status')->nullable()->after('message_type');
            $table->decimal('ai_confidence', 5, 2)->nullable()->after('status');
            $table->json('metadata')->nullable()->after('ai_confidence');
            $table->timestamp('delivered_at')->nullable()->after('metadata');
            $table->timestamp('read_at')->nullable()->after('delivered_at');

            $table->unique('external_message_id', 'messages_external_message_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_external_message_id_unique');
            $table->dropColumn([
                'text',
                'language',
                'channel',
                'external_message_id',
                'message_type',
                'status',
                'ai_confidence',
                'metadata',
                'delivered_at',
                'read_at',
            ]);
        });
    }
};
