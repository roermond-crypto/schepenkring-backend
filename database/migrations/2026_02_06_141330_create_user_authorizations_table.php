<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('user_authorizations', function (Blueprint $table) {
        $table->id();
        // Links to your existing users table [cite: 71, 74]
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); 
        // Stores permission strings like 'manage yachts' or 'manage tasks' [cite: 67, 70]
        $table->string('operation_name'); 
        $table->timestamps();
        
        // Prevents duplicate permission entries for the same user
        $table->unique(['user_id', 'operation_name']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_authorizations');
    }
};
