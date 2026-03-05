<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('last_inbound_at')->nullable()->after('last_customer_message_at');
            $table->timestamp('window_expires_at')->nullable()->after('last_inbound_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['last_inbound_at', 'window_expires_at']);
        });
    }
};
