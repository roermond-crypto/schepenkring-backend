<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 100);        // widget_opened, widget_plan_viewing_submitted, etc.
            $table->string('flow_type', 50)->nullable(); // plan_viewing, offer, brochure, callback, question
            $table->unsignedBigInteger('location_id')->nullable()->index();
            $table->unsignedBigInteger('boat_id')->nullable()->index();
            $table->string('visitor_id', 64)->nullable()->index();
            $table->string('source_url')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['event_name', 'created_at']);
            $table->index(['location_id', 'event_name', 'created_at']);
        });

        // Add widget-specific columns to bookings
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'chat_id')) {
                $table->string('chat_id')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (!Schema::hasColumn('bookings', 'preferred_time')) {
                $table->string('preferred_time', 10)->nullable();
            }
        });

        // Add offer_amount to conversations for offer flow
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'widget_flow_type')) {
                $table->string('widget_flow_type', 50)->nullable();
            }
            if (!Schema::hasColumn('conversations', 'widget_offer_amount')) {
                $table->decimal('widget_offer_amount', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_events');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(array_filter(['chat_id', 'lead_id', 'phone', 'preferred_time'], function ($col) {
                return Schema::hasColumn('bookings', $col);
            }));
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(array_filter(['widget_flow_type', 'widget_offer_amount'], function ($col) {
                return Schema::hasColumn('conversations', $col);
            }));
        });
    }
};
