<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('relationNumber')->nullable()->after('id');
        $table->string('firstName')->nullable()->after('name');
        $table->string('lastName')->nullable()->after('firstName');
        $table->string('prefix')->nullable()->after('lastName');
        $table->string('initials')->nullable()->after('prefix');
        $table->string('title')->nullable()->after('initials');
        $table->string('salutation')->nullable()->after('title');
        $table->string('attentionOf')->nullable()->after('salutation');
        $table->string('identification')->nullable()->after('attentionOf');
        $table->date('dateOfBirth')->nullable()->after('identification');
        $table->string('website')->nullable()->after('dateOfBirth');
        $table->string('mobile')->nullable()->after('website');
        $table->string('street')->nullable()->after('mobile');
        $table->string('houseNumber')->nullable()->after('street');
        $table->text('note')->nullable()->after('houseNumber');
        $table->unsignedInteger('claimHistoryCount')->default(0)->after('note');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn([
            'relationNumber',
            'firstName',
            'lastName',
            'prefix',
            'initials',
            'title',
            'salutation',
            'attentionOf',
            'identification',
            'dateOfBirth',
            'website',
            'mobile',
            'street',
            'houseNumber',
            'note',
            'claimHistoryCount',
        ]);
    });
}
};
