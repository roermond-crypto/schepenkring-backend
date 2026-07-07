<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sign_requests', function (Blueprint $table) {
            // Signing URLs per participant
            $table->text('signhost_buyer_link')->nullable()->after('signhost_transaction_id');
            $table->text('signhost_seller_link')->nullable()->after('signhost_buyer_link');

            // Transaction lifecycle timestamps
            $table->timestamp('signhost_created_at')->nullable()->after('signhost_seller_link');
            $table->timestamp('signhost_expires_at')->nullable()->after('signhost_created_at');
            $table->timestamp('signhost_last_checked_at')->nullable()->after('signhost_expires_at');

            // Participant signing timestamps
            $table->timestamp('buyer_signed_at')->nullable()->after('signhost_last_checked_at');
            $table->timestamp('seller_signed_at')->nullable()->after('buyer_signed_at');
            $table->timestamp('broker_signed_at')->nullable()->after('seller_signed_at');
            $table->timestamp('completed_at')->nullable()->after('broker_signed_at');

            // Signed PDF storage
            $table->string('signed_pdf_path')->nullable()->after('completed_at');
            $table->string('signed_pdf_hash')->nullable()->after('signed_pdf_path');

            // Raw Signhost API response (last fetch)
            $table->json('signhost_raw_response')->nullable()->after('signed_pdf_hash');

            // Webhook retry tracking
            $table->boolean('webhook_failed')->default(false)->after('signhost_raw_response');
            $table->text('webhook_error')->nullable()->after('webhook_failed');
            $table->json('webhook_last_payload')->nullable()->after('webhook_error');
        });
    }

    public function down(): void
    {
        Schema::table('sign_requests', function (Blueprint $table) {
            $table->dropColumn([
                'signhost_buyer_link',
                'signhost_seller_link',
                'signhost_created_at',
                'signhost_expires_at',
                'signhost_last_checked_at',
                'buyer_signed_at',
                'seller_signed_at',
                'broker_signed_at',
                'completed_at',
                'signed_pdf_path',
                'signed_pdf_hash',
                'signhost_raw_response',
                'webhook_failed',
                'webhook_error',
                'webhook_last_payload',
            ]);
        });
    }
};
