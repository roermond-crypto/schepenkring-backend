<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');  // purchase_contract, seller_assignment, viewing_agreement, offer_agreement, delivery_document, brokerage_agreement
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedBigInteger('parent_template_id')->nullable();
            $table->string('language')->default('nl');
            $table->longText('content_html');
            $table->json('content_json')->nullable();  // TipTap JSON
            $table->boolean('is_global')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->string('description')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->unsignedBigInteger('current_version')->default(1);
            $table->timestamps();
            $table->index(['type', 'location_id']);
            $table->index(['is_global', 'type']);
            $table->index(['location_id', 'is_default', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
