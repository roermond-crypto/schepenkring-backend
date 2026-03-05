<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\User;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('partner_token', 64)->nullable()->unique()->after('remember_token');
        });

        // Generate tokens for existing partners
        User::where('role', 'Partner')->each(function ($user) {
            $user->partner_token = Str::random(32);
            $user->save();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('partner_token');
        });
    }
};