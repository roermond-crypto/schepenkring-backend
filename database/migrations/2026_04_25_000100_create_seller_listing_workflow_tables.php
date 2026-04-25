<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boat_intakes')) {
            Schema::create('boat_intakes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 40)->default('draft_intake')->index();
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->decimal('length_m', 8, 2)->nullable();
                $table->decimal('width_m', 8, 2)->nullable();
                $table->decimal('height_m', 8, 2)->nullable();
                $table->string('fuel_type', 80)->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->text('description')->nullable();
                $table->string('boat_type', 120)->nullable();
                $table->json('photo_manifest_json')->nullable();
                $table->unsignedBigInteger('latest_payment_id')->nullable();
                $table->unsignedBigInteger('listing_workflow_id')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('boat_intake_payments')) {
            Schema::create('boat_intake_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('boat_intake_id')->constrained('boat_intakes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('mollie_payment_id')->nullable()->unique();
                $table->string('idempotency_key')->nullable()->index();
                $table->string('status', 40)->default('open')->index();
                $table->string('amount_currency', 3)->default('EUR');
                $table->decimal('amount_value', 10, 2)->default(0);
                $table->text('checkout_url')->nullable();
                $table->text('redirect_url')->nullable();
                $table->unsignedInteger('webhook_events_count')->default(0);
                $table->json('metadata_json')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('listing_workflows')) {
            Schema::create('listing_workflows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('boat_intake_id')->constrained('boat_intakes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('yacht_id')->nullable()->constrained('yachts')->nullOnDelete();
                $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 50)->default('paid')->index();
                $table->boolean('seller_verification_required')->default(false);
                $table->timestamp('seller_verification_expires_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('ai_generated_at')->nullable();
                $table->timestamp('admin_reviewed_at')->nullable();
                $table->timestamp('client_approved_at')->nullable();
                $table->timestamp('ready_to_publish_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->text('last_review_message')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('listing_workflow_versions')) {
            Schema::create('listing_workflow_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('listing_workflow_id')->constrained('listing_workflows')->cascadeOnDelete();
                $table->foreignId('yacht_id')->nullable()->constrained('yachts')->nullOnDelete();
                $table->string('version_type', 40)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('created_by_role', 40)->nullable();
                $table->json('payload_json');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('listing_workflow_reviews')) {
            Schema::create('listing_workflow_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('listing_workflow_id')->constrained('listing_workflows')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role', 40)->nullable();
                $table->string('action', 40)->index();
                $table->text('message')->nullable();
                $table->json('metadata_json')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_workflow_reviews');
        Schema::dropIfExists('listing_workflow_versions');
        Schema::dropIfExists('listing_workflows');
        Schema::dropIfExists('boat_intake_payments');
        Schema::dropIfExists('boat_intakes');
    }
};
