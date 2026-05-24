<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->default('phone'); // phone | chat | sms | email
            $table->string('phone_number')->nullable();
            $table->string('language')->default('nl');
            $table->string('vonage_call_id')->nullable();
            $table->string('openai_session_id')->nullable();
            $table->string('status')->default('initiated'); // initiated | active | ended
            $table->json('tags')->nullable();
            $table->json('events')->nullable();
            $table->text('transcript')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_sessions');
    }
};
