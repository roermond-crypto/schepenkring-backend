<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('seller_onboardings')
            ->where('decision', 'approved')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addYears(2)]);
    }

    public function down(): void {}
};
