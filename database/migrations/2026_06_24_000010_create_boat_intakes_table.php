<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('boat_intakes')) Schema::create('boat_intakes', function (Blueprint $table) {
            $table->id();

            // Seller contact (can be anonymous, linked to user after creation)
            $table->unsignedBigInteger('seller_user_id')->nullable();
            $table->foreign('seller_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('seller_first_name')->nullable();
            $table->string('seller_last_name')->nullable();
            $table->string('seller_email')->nullable();
            $table->string('seller_phone', 50)->nullable();

            // Seller address
            $table->string('seller_address')->nullable();
            $table->string('seller_postal_code', 20)->nullable();
            $table->string('seller_city')->nullable();
            $table->string('seller_country', 10)->default('NL');
            $table->decimal('seller_lat', 10, 7)->nullable();
            $table->decimal('seller_lng', 10, 7)->nullable();

            // Location assignment
            $table->unsignedBigInteger('location_id')->nullable();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();

            // Boat details
            $table->string('boat_brand')->nullable();
            $table->string('boat_model')->nullable();
            $table->string('boat_name')->nullable();
            $table->year('build_year')->nullable();
            $table->decimal('length_m', 6, 2)->nullable();
            $table->decimal('width_m', 5, 2)->nullable();
            $table->decimal('draft_m', 5, 2)->nullable();
            $table->decimal('asking_price', 12, 2)->nullable();
            $table->string('vat_status', 40)->nullable(); // included | excluded | marginal | unknown
            $table->string('ce_category', 10)->nullable(); // A | B | C | D | none | unknown
            $table->string('boat_type')->nullable();
            $table->text('short_description')->nullable();

            // Result after successful intake
            $table->unsignedBigInteger('yacht_id')->nullable();
            $table->foreign('yacht_id')->references('id')->on('yachts')->nullOnDelete();

            // Status lifecycle
            // new → submitted → frontend_draft → missing_information → ready_for_admin_review → accepted → rejected
            $table->string('status', 40)->default('new');

            // Completeness scoring (0-100)
            $table->decimal('intake_score', 5, 2)->nullable();
            $table->json('score_breakdown')->nullable(); // {"photos":60,"fields":70,"description":40,"documents":50}
            $table->json('missing_items')->nullable();   // ["ce_certificate","vat_document","more_photos"]

            // Counts
            $table->unsignedSmallInteger('photo_count')->default(0);
            $table->unsignedSmallInteger('document_count')->default(0);
            $table->unsignedSmallInteger('description_length')->default(0);

            // Resume token for email link (unauthenticated seller can return to their intake)
            $table->string('resume_token', 64)->nullable()->unique();
            $table->timestamp('resume_token_expires_at')->nullable();

            // Email tracking
            $table->boolean('confirmation_sent')->default(false);
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();

            // Source
            $table->string('source_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['status']);
            $table->index(['location_id', 'status']);
            $table->index('seller_email');
            $table->index('resume_token');
        });

        // Documents uploaded during intake
        if (!Schema::hasTable('boat_intake_files')) Schema::create('boat_intake_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boat_intake_id');
            $table->foreign('boat_intake_id')->references('id')->on('boat_intakes')->cascadeOnDelete();

            $table->string('type', 40)->default('photo'); // photo | ce_certificate | vat_document | invoice | registration | maintenance | other
            $table->string('original_name');
            $table->string('path');                       // storage path
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes
            $table->string('disk', 20)->default('public');

            // For photos: auto-crop metadata
            $table->string('variant', 20)->nullable(); // original | thumb | cropped

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['boat_intake_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_intake_files');
        Schema::dropIfExists('boat_intakes');
    }
};
