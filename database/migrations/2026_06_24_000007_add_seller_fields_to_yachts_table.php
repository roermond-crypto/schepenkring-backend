<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->unsignedBigInteger('seller_id')->nullable()->after('user_id');
            $table->foreign('seller_id')->references('id')->on('users')->nullOnDelete();

            $table->decimal('minimum_offer_amount', 12, 2)->nullable()->after('seller_id');
            $table->boolean('seller_login_enabled')->default(false)->after('minimum_offer_amount');
            $table->boolean('seller_invite_enabled')->default(false)->after('seller_login_enabled');
            $table->boolean('seller_email_notifications')->default(true)->after('seller_invite_enabled');
            $table->boolean('seller_counter_offer_enabled')->default(true)->after('seller_email_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn([
                'seller_id',
                'minimum_offer_amount',
                'seller_login_enabled',
                'seller_invite_enabled',
                'seller_email_notifications',
                'seller_counter_offer_enabled',
            ]);
        });
    }
};
