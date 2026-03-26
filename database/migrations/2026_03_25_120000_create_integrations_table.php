<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('integration_type');                          // e.g. telnyx, 360dialog, mollie
            $table->string('label')->nullable();                         // friendly display name
            $table->string('username')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->string('environment')->default('live');              // test | live
            $table->string('status')->default('active');                 // active | inactive
            $table->unsignedBigInteger('location_id')->nullable();
            $table->timestamps();

            $table->index(['integration_type', 'status', 'environment']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
