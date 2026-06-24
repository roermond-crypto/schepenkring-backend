<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();

            // Core relationships
            $table->unsignedBigInteger('yacht_id');
            $table->foreign('yacht_id')->references('id')->on('yachts')->cascadeOnDelete();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->foreign('seller_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('buyer_id')->nullable(); // User account if registered
            $table->foreign('buyer_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->string('conversation_id')->nullable(); // UUID FK to conversations

            // Buyer info (for anonymous/guest buyers)
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_phone', 50)->nullable();

            // Offer amounts
            $table->decimal('amount', 12, 2)->nullable();           // Current offer amount
            $table->decimal('asking_price', 12, 2)->nullable();     // Snapshot at time of offer
            $table->decimal('minimum_amount', 12, 2)->nullable();   // Snapshot of minimum

            // Status: new | sent_to_seller | seller_accepted | seller_rejected | seller_countered | buyer_replied | withdrawn | completed
            $table->string('status', 40)->default('new');

            // Counter-offer chain
            $table->decimal('counter_amount', 12, 2)->nullable();
            $table->text('counter_message')->nullable();
            $table->unsignedBigInteger('parent_offer_id')->nullable(); // For counter-offer chain
            $table->foreign('parent_offer_id')->references('id')->on('offers')->nullOnDelete();

            // Buyer message
            $table->text('message')->nullable();

            // Flags
            $table->boolean('below_minimum')->default(false);
            $table->boolean('seller_notified')->default(false);
            $table->timestamp('seller_notified_at')->nullable();
            $table->timestamp('seller_responded_at')->nullable();

            // Source
            $table->string('source', 40)->default('widget'); // widget | admin | phone | email
            $table->string('source_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['yacht_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index(['location_id', 'status']);
            $table->index('buyer_email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
