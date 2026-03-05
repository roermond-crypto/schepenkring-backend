<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('status')->nullable()->after('message_type');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unique('external_message_id', 'messages_external_message_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_external_message_id_unique');
            $table->dropColumn('status');
        });
    }
};
