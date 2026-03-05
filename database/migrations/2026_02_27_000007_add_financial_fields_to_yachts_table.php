<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->decimal('sale_price', 15, 2)->nullable()->after('price');
            $table->decimal('commission_percentage', 5, 2)->nullable()->after('sale_price');
            $table->decimal('harbor_split_percentage', 5, 2)->nullable()->after('commission_percentage');
            $table->decimal('commission_amount', 15, 2)->nullable()->after('harbor_split_percentage');
            $table->enum('sale_stage', [
                'draft',
                'listed',
                'offer_received',
                'offer_accepted',
                'in_escrow',
                'delivered',
                'cancelled',
            ])->default('draft')->after('commission_amount')->index();
            $table->timestamp('commission_calculated_at')->nullable()->after('sale_stage');
        });

        DB::statement("UPDATE yachts SET sale_price = price WHERE sale_price IS NULL");
        DB::statement("UPDATE yachts SET sale_stage = CASE status
            WHEN 'Draft' THEN 'draft'
            WHEN 'For Sale' THEN 'listed'
            WHEN 'For Bid' THEN 'offer_received'
            WHEN 'Sold' THEN 'delivered'
            ELSE 'draft'
        END");
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn([
                'sale_price',
                'commission_percentage',
                'harbor_split_percentage',
                'commission_amount',
                'sale_stage',
                'commission_calculated_at',
            ]);
        });
    }
};
