<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('boat_id')->nullable()->after('location_id');
            $table->uuid('contact_id')->nullable()->after('boat_id');
            $table->string('visitor_id')->nullable()->after('contact_id');
            $table->string('priority')->default('normal')->after('status');
            $table->string('channel_origin')->default('web_widget')->after('channel');
            $table->string('ai_mode')->default('auto')->after('channel_origin');
            $table->string('language_preferred', 5)->nullable()->after('ai_mode');
            $table->string('language_detected', 5)->nullable()->after('language_preferred');
            $table->text('page_url')->nullable()->after('language_detected');
            $table->string('utm_source')->nullable()->after('page_url');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('ref_code')->nullable()->after('utm_campaign');
            $table->timestamp('last_message_at')->nullable()->after('ref_code');
            $table->timestamp('last_customer_message_at')->nullable()->after('last_message_at');
            $table->timestamp('last_inbound_at')->nullable()->after('last_customer_message_at');
            $table->timestamp('window_expires_at')->nullable()->after('last_inbound_at');
            $table->timestamp('last_staff_message_at')->nullable()->after('window_expires_at');
            $table->timestamp('last_call_at')->nullable()->after('last_staff_message_at');
            $table->timestamp('first_response_due_at')->nullable()->after('last_call_at');
            $table->unsignedBigInteger('assigned_to')->nullable()->after('first_response_due_at');

            $table->index('last_message_at');
            $table->index('visitor_id');
            $table->index('contact_id');
            $table->index('assigned_to');
            $table->index('user_id');

            $table->foreign('boat_id')->references('id')->on('yachts')->nullOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['boat_id']);
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['user_id']);

            $table->dropIndex(['last_message_at']);
            $table->dropIndex(['visitor_id']);
            $table->dropIndex(['contact_id']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['user_id']);

            $table->dropColumn([
                'user_id',
                'boat_id',
                'contact_id',
                'visitor_id',
                'priority',
                'channel_origin',
                'ai_mode',
                'language_preferred',
                'language_detected',
                'page_url',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'ref_code',
                'last_message_at',
                'last_customer_message_at',
                'last_inbound_at',
                'window_expires_at',
                'last_staff_message_at',
                'last_call_at',
                'first_response_due_at',
                'assigned_to',
            ]);
        });
    }
};
