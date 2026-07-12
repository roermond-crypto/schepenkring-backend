<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // subject_id was originally unsignedBigInteger on both tables, but
        // several subject types (call_session, and the Chat Hub's
        // conversation) use UUID primary keys, not integers — a UUID
        // subject_id would fail to insert. Widened to string so it can
        // hold either. Caught during Milestone 6's audit-logging pass,
        // before either table had real data in any environment.
        Schema::table('activity_events', function (Blueprint $table) {
            $table->string('subject_id')->change();
        });
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->string('subject_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_events', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->change();
        });
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->change();
        });
    }
};
