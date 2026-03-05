<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
// database/migrations/xxxx_add_lockscreen_timeout_to_users_table.php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->unsignedSmallInteger('lockscreen_timeout')->default(10)->after('password');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('lockscreen_timeout');
    });
}
};
