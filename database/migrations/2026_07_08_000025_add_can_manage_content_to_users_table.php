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
            $table->boolean('can_manage_content')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_manage_content');
        });
    }
};
