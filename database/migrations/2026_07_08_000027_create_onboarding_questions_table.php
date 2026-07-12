<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_questions', function (Blueprint $table) {
            $table->id();
            $table->string('audience')->default('both'); // seller | buyer | both
            // Free-text grouping key matching the panels' existing step
            // concept (SellerOnboardingPanel/BuyerVerificationPanel use
            // 'profile'/'kyc'/'complete') — admin decides which step a
            // custom question appears under.
            $table->string('step_key')->default('profile');
            $table->string('field_type')->default('text'); // text|textarea|date|select|checkbox|radio
            $table->json('label'); // {nl,en,de,fr}
            $table->json('help_text')->nullable();
            $table->json('placeholder')->nullable();
            // [{value: string, label: {nl,en,de,fr}}] — only meaningful for
            // select/radio/checkbox.
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['audience', 'active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_questions');
    }
};
