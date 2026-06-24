<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── KYC cases ────────────────────────────────────────────
        Schema::create('kyc_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 30)->unique(); // KYC-2026-118

            // Linked entities (all nullable — case can be created before deal exists)
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->foreign('deal_id')->references('id')->on('deals')->nullOnDelete();

            $table->unsignedBigInteger('offer_id')->nullable();
            $table->foreign('offer_id')->references('id')->on('offers')->nullOnDelete();

            $table->unsignedBigInteger('contract_id')->nullable();
            $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();

            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->foreign('buyer_id')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('seller_id')->nullable();
            $table->foreign('seller_id')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('yacht_id')->nullable();
            $table->foreign('yacht_id')->references('id')->on('yachts')->nullOnDelete();

            $table->unsignedBigInteger('location_id')->nullable();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();

            $table->unsignedBigInteger('broker_id')->nullable();
            $table->foreign('broker_id')->references('id')->on('users')->nullOnDelete();

            // Status & risk
            $table->enum('status', [
                'draft', 'in_progress', 'waiting_documents', 'under_review',
                'approved', 'rejected', 'high_risk', 'reported',
            ])->default('draft');
            $table->unsignedInteger('risk_score')->default(0);
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->boolean('blocking')->default(false);

            // Approval
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            // Auto-filled buyer snapshot
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->string('buyer_address')->nullable();
            $table->string('buyer_city')->nullable();
            $table->string('buyer_country', 5)->nullable();
            $table->string('buyer_iban', 50)->nullable();
            $table->string('buyer_dob', 20)->nullable(); // date of birth

            // Auto-filled seller snapshot
            $table->string('seller_name')->nullable();
            $table->string('seller_email')->nullable();
            $table->string('seller_phone')->nullable();
            $table->string('seller_address')->nullable();

            // Auto-filled boat snapshot
            $table->string('boat_name')->nullable();
            $table->string('boat_type')->nullable();
            $table->decimal('boat_value', 14, 2)->nullable();

            // Deal snapshot
            $table->decimal('deal_value', 14, 2)->nullable();
            $table->string('payment_method')->nullable(); // bank, cash, crypto, combination

            $table->boolean('auto_created')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── KYC question templates ────────────────────────────────
        Schema::create('kyc_question_templates', function (Blueprint $table) {
            $table->id();
            $table->string('section', 100);
            $table->text('question');
            $table->text('action')->nullable(); // compliance action to take
            $table->enum('field_type', [
                'yes_no', 'dropdown', 'text', 'textarea', 'date',
                'number', 'money', 'country', 'document', 'photo', 'signature',
            ])->default('yes_no');
            $table->json('options')->nullable(); // for dropdown
            $table->integer('risk_points')->default(0);
            $table->enum('risk_flag', ['none', 'warning', 'blocking', 'critical'])->default('none');
            $table->boolean('required')->default(false);
            // Conditional display
            $table->unsignedBigInteger('conditional_on_question_id')->nullable();
            $table->foreign('conditional_on_question_id')->references('id')->on('kyc_question_templates')->nullOnDelete();
            $table->string('conditional_show_when', 20)->nullable(); // e.g. "yes", "true", "cash"
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['section', 'sort_order']);
        });

        // ── KYC answers ──────────────────────────────────────────
        Schema::create('kyc_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kyc_case_id');
            $table->foreign('kyc_case_id')->references('id')->on('kyc_cases')->cascadeOnDelete();
            $table->unsignedBigInteger('question_template_id');
            $table->foreign('question_template_id')->references('id')->on('kyc_question_templates')->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->text('note')->nullable();
            $table->integer('risk_points_awarded')->default(0);
            $table->unsignedBigInteger('answered_by')->nullable();
            $table->foreign('answered_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['kyc_case_id', 'question_template_id']);
        });

        // ── KYC documents ────────────────────────────────────────
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kyc_case_id');
            $table->foreign('kyc_case_id')->references('id')->on('kyc_cases')->cascadeOnDelete();
            $table->enum('party', ['buyer', 'seller', 'boat'])->default('buyer');
            $table->string('document_type'); // passport, driving_license, proof_of_address, etc.
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk', 30)->default('private'); // private by default for KYC
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->boolean('verified')->default(false);
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->json('ocr_data')->nullable(); // future OCR extraction results
            $table->timestamps();

            $table->index(['kyc_case_id', 'party', 'document_type']);
        });

        // ── KYC timeline ─────────────────────────────────────────
        Schema::create('kyc_timeline', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kyc_case_id');
            $table->foreign('kyc_case_id')->references('id')->on('kyc_cases')->cascadeOnDelete();
            $table->string('event', 100); // e.g. passport_uploaded, risk_score_changed, approved
            $table->string('description')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->string('actor_name', 150)->nullable(); // snapshot in case user deleted
            $table->json('meta')->nullable(); // old_value, new_value, etc.
            $table->timestamps();

            $table->index(['kyc_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_timeline');
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('kyc_answers');
        Schema::dropIfExists('kyc_question_templates');
        Schema::dropIfExists('kyc_cases');
    }
};
