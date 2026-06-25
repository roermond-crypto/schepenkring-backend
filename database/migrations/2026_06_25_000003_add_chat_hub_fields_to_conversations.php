<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('chat_type', 30)->nullable()->after('channel')
                ->comment('general|offer|viewing|callback|question|brochure|seller|buyer');

            $table->string('send_channel', 20)->nullable()->after('chat_type')
                ->comment('chat|email|whatsapp');

            // Linked business objects
            $table->unsignedBigInteger('offer_id')->nullable()->after('boat_id');
            $table->unsignedBigInteger('booking_id')->nullable()->after('offer_id');
            $table->unsignedBigInteger('brochure_id')->nullable()->after('booking_id');
            $table->unsignedBigInteger('question_id')->nullable()->after('brochure_id');
            $table->unsignedBigInteger('callback_id')->nullable()->after('question_id');
            $table->unsignedBigInteger('buyer_id')->nullable()->after('callback_id');
            $table->unsignedBigInteger('seller_id')->nullable()->after('buyer_id');

            // SLA: time the conversation was last waiting for a staff reply
            $table->timestamp('waiting_since')->nullable()->after('last_customer_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn([
                'chat_type',
                'send_channel',
                'offer_id',
                'booking_id',
                'brochure_id',
                'question_id',
                'callback_id',
                'buyer_id',
                'seller_id',
                'waiting_since',
            ]);
        });
    }
};
