<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every Lead::create() call site in the app (WidgetLeadController's 5 widget
 * flows, the admin LeadController, RetellToolActionController) passes a
 * 'notes' attribute — 'notes' has been in Lead::$fillable since the leads
 * table was created, but no migration ever actually added the column. Every
 * one of those inserts has been throwing a fatal "Unknown column 'notes'"
 * SQL error, and since the widget flows wrap lead creation in a
 * DB::transaction(), the whole submission (conversation + lead + booking)
 * rolls back — meaning public widget submissions have been silently
 * failing entirely, not just losing this one field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
