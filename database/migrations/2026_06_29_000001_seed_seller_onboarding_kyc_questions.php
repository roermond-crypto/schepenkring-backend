<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('db:seed', ['--class' => 'SellerOnboardingKycSeeder', '--force' => true]);
    }

    public function down(): void
    {
        // Questions are upserted; no rollback needed
    }
};
