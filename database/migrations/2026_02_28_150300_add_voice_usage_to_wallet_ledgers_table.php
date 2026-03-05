<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE wallet_ledgers MODIFY COLUMN type ENUM('COMMISSION_PENDING','COMMISSION_REALIZED','HARBOR_SPLIT','LISTING_FEE','REFUND','PAYOUT','CORRECTION','LOCKED','VOICE_USAGE') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE wallet_ledgers MODIFY COLUMN type ENUM('COMMISSION_PENDING','COMMISSION_REALIZED','HARBOR_SPLIT','LISTING_FEE','REFUND','PAYOUT','CORRECTION','LOCKED') NOT NULL");
    }
};
