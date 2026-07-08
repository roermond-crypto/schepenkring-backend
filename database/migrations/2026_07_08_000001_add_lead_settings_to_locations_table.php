<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->after('phone');
            $table->string('sender_email')->nullable()->after('email');
            // How new leads/chats/bookings are assigned when a boat has no
            // seller of its own: default_seller (use the field below),
            // round_robin (rotate across active location staff), or
            // unassigned (always land in the location inbox for manual pickup).
            $table->string('lead_assignment_mode')->default('default_seller')->after('default_seller_id');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'sender_email', 'lead_assignment_mode']);
        });
    }
};
