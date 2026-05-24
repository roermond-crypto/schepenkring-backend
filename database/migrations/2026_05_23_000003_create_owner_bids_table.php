<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yacht_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // buyer
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_bid_id')->nullable()->constrained('owner_bids')->nullOnDelete();
            $table->string('type')->default('offer'); // offer | counter
            $table->decimal('amount', 12, 2);
            $table->text('message')->nullable();
            $table->string('status')->default('pending'); // pending | accepted | rejected | countered
            $table->timestamps();
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_bid_id')->constrained()->cascadeOnDelete();
            $table->foreignId('yacht_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('conversation_id')->nullable();
            $table->decimal('agreed_amount', 12, 2);
            $table->string('status')->default('active'); // active | closed | cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
        Schema::dropIfExists('owner_bids');
    }
};
