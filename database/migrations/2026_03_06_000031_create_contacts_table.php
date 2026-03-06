<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->string('whatsapp_user_id')->nullable()->unique();
            $table->string('language_preferred', 5)->nullable();
            $table->boolean('do_not_contact')->default(false);
            $table->boolean('consent_marketing')->default(false);
            $table->boolean('consent_service_messages')->default(false);
            $table->timestamps();

            $table->index('do_not_contact');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
