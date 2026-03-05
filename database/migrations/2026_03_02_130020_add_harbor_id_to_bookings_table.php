<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'harbor_id')) {
                $table->unsignedBigInteger('harbor_id')->nullable()->after('yacht_id');
                $table->index('harbor_id');
                $table->foreign('harbor_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('bookings', 'harbor_id')) {
            DB::table('bookings')
                ->leftJoin('yachts', 'bookings.yacht_id', '=', 'yachts.id')
                ->whereNull('bookings.harbor_id')
                ->update(['bookings.harbor_id' => DB::raw('yachts.user_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'harbor_id')) {
                $table->dropForeign(['harbor_id']);
                $table->dropIndex(['harbor_id']);
                $table->dropColumn('harbor_id');
            }
        });
    }
};
