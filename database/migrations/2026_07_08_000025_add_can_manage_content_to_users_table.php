<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A sub-permission within admin, not a new UserType — the audit
            // found no "super admin"/"content manager" tier anywhere, and
            // adding a whole new role case would touch every existing role
            // check across the app. This is deliberately the smaller,
            // narrower change: not every admin should see Global Edit Mode,
            // but it's still gated behind isAdmin() first.
            //
            // Positioned after `status`, not `is_active` — despite
            // User::casts() listing 'is_active' => 'boolean', no migration
            // ever actually created that column (confirmed by checking
            // every users-table migration); it's stale dead code in the
            // model, not a real column.
            $table->boolean('can_manage_content')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_manage_content');
        });
    }
};
