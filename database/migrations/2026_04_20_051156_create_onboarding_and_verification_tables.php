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
        // --- SELLER PROFILES ---
        if (!Schema::hasTable('seller_profiles')) {
            Schema::create('seller_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users', 'id', 'sp_user_fk')->cascadeOnDelete();
                $table->string('seller_type', 20)->nullable()->index('sp_type_idx');
                $table->string('full_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country', 2)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('iban')->nullable();
                $table->string('company_name')->nullable();
                $table->string('kvk_number')->nullable();
                $table->string('verified_full_name')->nullable();
                $table->string('verified_iban')->nullable();
                $table->string('verified_bank_account_holder')->nullable();
                $table->timestamp('identity_verified_at')->nullable();
                $table->timestamp('bank_verified_at')->nullable();
                $table->timestamps();
                $table->unique('user_id', 'sp_user_unique');
            });
        }

        // --- BUYER PROFILES ---
        if (!Schema::hasTable('buyer_profiles')) {
            Schema::create('buyer_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users', 'id', 'bp_user_fk')->cascadeOnDelete();
                $table->string('buyer_type', 20)->nullable()->index('bp_type_idx');
                $table->string('full_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('address_line_1')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country', 2)->nullable();
                $table->date('birth_date')->nullable();
                $table->string('iban')->nullable();
                $table->string('company_name')->nullable();
                $table->string('kvk_number')->nullable();
                $table->string('verified_full_name')->nullable();
                $table->string('verified_iban')->nullable();
                $table->string('verified_bank_account_holder')->nullable();
                $table->timestamp('identity_verified_at')->nullable();
                $table->timestamp('bank_verified_at')->nullable();
                $table->timestamps();
                $table->unique('user_id', 'bp_user_unique');
            });
        }

        // --- SELLER ONBOARDINGS ---
        if (!Schema::hasTable('seller_onboardings')) {
            Schema::create('seller_onboardings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users', 'id', 'so_user_fk')->cascadeOnDelete();
                $table->string('status', 40)->default('CREATED')->index('so_status_idx');
                $table->string('payment_status', 40)->default('pending');
                $table->string('idin_status', 40)->default('pending');
                $table->string('ideal_status', 40)->default('pending');
                $table->string('kyc_status', 40)->default('pending');
                $table->string('contract_status', 40)->default('pending');
                $table->unsignedInteger('risk_score')->default(0);
                $table->boolean('manual_review_required')->default(false);
                $table->string('decision', 40)->nullable()->index('so_decision_idx');
                $table->text('decision_reason')->nullable();
                $table->boolean('can_publish_boat')->default(false)->index('so_publish_idx');
                $table->json('reason_codes_json')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index('so_expires_idx');
                $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id', 'so_reviewed_by_fk')->nullOnDelete();
                $table->unsignedBigInteger('latest_contract_id')->nullable();
                $table->unsignedBigInteger('latest_signhost_phase_id')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status'], 'so_lookup_idx');
            });
        }

        // --- BUYER VERIFICATIONS ---
        if (!Schema::hasTable('buyer_verifications')) {
            Schema::create('buyer_verifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users', 'id', 'bv_user_fk')->cascadeOnDelete();
                $table->string('status', 40)->default('CREATED')->index('bv_status_idx');
                $table->string('idin_status', 40)->default('pending');
                $table->string('ideal_status', 40)->default('pending');
                $table->string('kyc_status', 40)->default('pending');
                $table->unsignedInteger('risk_score')->default(0);
                $table->boolean('manual_review_required')->default(false);
                $table->string('decision', 40)->nullable()->index('bv_decision_idx');
                $table->text('decision_reason')->nullable();
                $table->json('reason_codes_json')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index('bv_expires_idx');
                $table->foreignId('reviewed_by')->nullable()->constrained('users', 'id', 'bv_reviewed_by_fk')->nullOnDelete();
                $table->unsignedBigInteger('latest_signhost_phase_id')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status'], 'bv_lookup_idx');
            });
        }

        // --- KYC QUESTIONS & RULES ---
        if (!Schema::hasTable('kyc_questions')) {
            Schema::create('kyc_questions', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique('kq_key_unique');
                $table->string('prompt');
                $table->string('input_type', 40)->default('single_choice');
                $table->string('audience', 20)->default('seller')->index('kq_audience_idx');
                $table->string('seller_type_scope', 20)->default('all')->index('kq_scope_idx');
                $table->boolean('required')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('conditions_json')->nullable();
                $table->boolean('is_active')->default(true)->index('kq_active_idx');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('kyc_question_options')) {
            Schema::create('kyc_question_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kyc_question_id')->constrained('kyc_questions', 'id', 'kqo_question_fk')->cascadeOnDelete();
                $table->string('value');
                $table->string('label');
                $table->unsignedInteger('sort_order')->default(0);
                $table->integer('score_delta')->default(0);
                $table->string('flag_code', 80)->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();
                $table->unique(['kyc_question_id', 'value'], 'kqo_unique');
            });
        }

        if (!Schema::hasTable('kyc_rules')) {
            Schema::create('kyc_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('audience', 20)->default('both')->index('kr_audience_idx');
                $table->json('conditions_json');
                $table->integer('score_delta')->default(0);
                $table->string('flag_code', 80)->nullable();
                $table->string('outcome_override', 40)->nullable();
                $table->unsignedInteger('priority')->default(100);
                $table->boolean('is_active')->default(true)->index('kr_active_idx');
                $table->timestamps();
            });
        }

        // --- PAYMENTS & CONTRACTS ---
        if (!Schema::hasTable('seller_onboarding_payments')) {
            Schema::create('seller_onboarding_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_onboarding_id')->constrained('seller_onboardings', 'id', 'sop_onboarding_fk')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users', 'id', 'sop_user_fk')->cascadeOnDelete();
                $table->string('type', 40)->default('seller_onboarding');
                $table->string('mollie_payment_id')->nullable()->unique('sop_mollie_unique');
                $table->string('idempotency_key')->nullable()->index('sop_idem_idx');
                $table->string('amount_currency', 3)->default('EUR');
                $table->decimal('amount_value', 10, 2)->default(0);
                $table->string('status', 40)->default('open')->index('sop_status_idx');
                $table->text('checkout_url')->nullable();
                $table->unsignedInteger('webhook_events_count')->default(0);
                $table->json('metadata_json')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('seller_onboarding_contracts')) {
            Schema::create('seller_onboarding_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_onboarding_id')->constrained('seller_onboardings', 'id', 'soc_onboarding_fk')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users', 'id', 'soc_user_fk')->cascadeOnDelete();
                $table->uuid('contract_uid')->unique('soc_uid_unique');
                $table->string('contract_type', 80)->default('seller_onboarding_agreement');
                $table->string('template_version', 40)->default('v1');
                $table->string('contract_pdf_path')->nullable();
                $table->string('contract_sha256')->nullable();
                $table->string('signed_document_path')->nullable();
                $table->string('signhost_transaction_id')->nullable()->index('soc_st_idx');
                $table->text('sign_url')->nullable();
                $table->string('status', 40)->default('pending')->index('soc_status_idx');
                $table->json('contract_payload')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamps();
            });
        }

        // --- SIGNHOST TRANSACTIONS ---
        if (!Schema::hasTable('seller_onboarding_signhost_transactions')) {
            Schema::create('seller_onboarding_signhost_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_onboarding_id')->constrained('seller_onboardings', 'id', 'sost_onboarding_fk')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users', 'id', 'sost_user_fk')->cascadeOnDelete();
                $table->unsignedBigInteger('seller_onboarding_contract_id')->nullable();
                $table->string('phase_type', 40)->index('sost_phase_idx');
                $table->string('provider_step', 40)->index('sost_step_idx');
                $table->string('signhost_transaction_id')->nullable()->unique('sost_tx_unique');
                $table->string('status', 40)->default('pending')->index('sost_status_idx');
                $table->text('redirect_url')->nullable();
                $table->json('payload_json')->nullable();
                $table->json('provider_response_json')->nullable();
                $table->json('webhook_last_payload')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('buyer_verification_signhost_transactions')) {
            Schema::create('buyer_verification_signhost_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_verification_id')->constrained('buyer_verifications', 'id', 'bvst_verification_fk')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users', 'id', 'bvst_user_fk')->cascadeOnDelete();
                $table->string('phase_type', 40)->index('bvst_phase_idx');
                $table->string('provider_step', 40)->index('bvst_step_idx');
                $table->string('signhost_transaction_id')->nullable()->unique('bvst_tx_unique');
                $table->string('status', 40)->default('pending')->index('bvst_status_idx');
                $table->text('redirect_url')->nullable();
                $table->json('payload_json')->nullable();
                $table->json('provider_response_json')->nullable();
                $table->json('webhook_last_payload')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // --- ANSWERS, FLAGS & REVIEWS ---
        if (!Schema::hasTable('seller_onboarding_kyc_answers')) {
            Schema::create('seller_onboarding_kyc_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_onboarding_id')->constrained('seller_onboardings', 'id', 'soka_onboarding_fk')->cascadeOnDelete();
                $table->foreignId('kyc_question_id')->constrained('kyc_questions', 'id', 'soka_question_fk')->cascadeOnDelete();
                $table->foreignId('kyc_question_option_id')->nullable()->constrained('kyc_question_options', 'id', 'soka_option_fk')->nullOnDelete();
                $table->string('question_key');
                $table->text('answer_value')->nullable();
                $table->text('normalized_value')->nullable();
                $table->json('answer_payload')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->unique(['seller_onboarding_id', 'kyc_question_id'], 'soka_unique');
            });
        }

        if (!Schema::hasTable('buyer_verification_kyc_answers')) {
            Schema::create('buyer_verification_kyc_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_verification_id')->constrained('buyer_verifications', 'id', 'bvka_verification_fk')->cascadeOnDelete();
                $table->foreignId('kyc_question_id')->constrained('kyc_questions', 'id', 'bvka_question_fk')->cascadeOnDelete();
                $table->foreignId('kyc_question_option_id')->nullable()->constrained('kyc_question_options', 'id', 'bvka_option_fk')->nullOnDelete();
                $table->string('question_key');
                $table->text('answer_value')->nullable();
                $table->text('normalized_value')->nullable();
                $table->json('answer_payload')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->unique(['buyer_verification_id', 'kyc_question_id'], 'bvka_unique');
            });
        }

        if (!Schema::hasTable('seller_onboarding_flags')) {
            Schema::create('seller_onboarding_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_onboarding_id')->constrained('seller_onboardings', 'id', 'sof_onboarding_fk')->cascadeOnDelete();
                $table->string('flag_code', 80)->index('sof_code_idx');
                $table->string('severity', 20)->default('warning');
                $table->text('message')->nullable();
                $table->json('metadata_json')->nullable();
                $table->boolean('is_blocking')->default(false);
                $table->timestamps();
                $table->unique(['seller_onboarding_id', 'flag_code'], 'sof_unique');
            });
        }

        if (!Schema::hasTable('buyer_verification_flags')) {
            Schema::create('buyer_verification_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_verification_id')->constrained('buyer_verifications', 'id', 'bvf_verification_fk')->cascadeOnDelete();
                $table->string('flag_code', 80)->index('bvf_code_idx');
                $table->string('severity', 20)->default('warning');
                $table->text('message')->nullable();
                $table->json('metadata_json')->nullable();
                $table->boolean('is_blocking')->default(false);
                $table->timestamps();
                $table->unique(['buyer_verification_id', 'flag_code'], 'bvf_unique');
            });
        }

        if (!Schema::hasTable('seller_onboarding_reviews')) {
            Schema::create('seller_onboarding_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seller_onboarding_id')->constrained('seller_onboardings', 'id', 'sor_onboarding_fk')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->nullable()->constrained('users', 'id', 'sor_reviewer_fk')->nullOnDelete();
                $table->string('status', 40)->default('open')->index('sor_status_idx');
                $table->string('outcome', 40)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('buyer_verification_reviews')) {
            Schema::create('buyer_verification_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_verification_id')->constrained('buyer_verifications', 'id', 'bvr_verification_fk')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->nullable()->constrained('users', 'id', 'bvr_reviewer_fk')->nullOnDelete();
                $table->string('status', 40)->default('open')->index('bvr_status_idx');
                $table->string('outcome', 40)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_verification_reviews');
        Schema::dropIfExists('seller_onboarding_reviews');
        Schema::dropIfExists('buyer_verification_flags');
        Schema::dropIfExists('seller_onboarding_flags');
        Schema::dropIfExists('buyer_verification_kyc_answers');
        Schema::dropIfExists('seller_onboarding_kyc_answers');
        Schema::dropIfExists('buyer_verification_signhost_transactions');
        Schema::dropIfExists('seller_onboarding_signhost_transactions');
        Schema::dropIfExists('seller_onboarding_contracts');
        Schema::dropIfExists('seller_onboarding_payments');
        Schema::dropIfExists('kyc_rules');
        Schema::dropIfExists('kyc_question_options');
        Schema::dropIfExists('kyc_questions');
        Schema::dropIfExists('buyer_verifications');
        Schema::dropIfExists('seller_onboardings');
        Schema::dropIfExists('buyer_profiles');
        Schema::dropIfExists('seller_profiles');
    }
};
