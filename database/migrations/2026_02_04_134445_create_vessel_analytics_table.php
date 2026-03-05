<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('vessel_analytics', function (Blueprint $table) {
        $table->id();
        $table->string('external_id')->index(); 
        $table->string('name')->nullable();
        $table->string('model')->nullable();
        $table->string('price')->nullable();
        $table->string('ref_code')->nullable();
        $table->string('url');
        $table->string('ip_address');
        $table->string('user_agent');
        $table->json('raw_specs')->nullable(); 
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vessel_analytics');
    }
};
