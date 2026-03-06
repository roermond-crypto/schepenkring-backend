<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('type')->default('CLIENT')->after('password');
            $table->string('status')->default('ACTIVE')->after('type');
            $table->string('phone')->nullable()->after('email');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->date('date_of_birth')->nullable()->after('last_name');
            $table->foreignId('client_location_id')->nullable()->after('remember_token')->constrained('locations');
            $table->string('timezone')->nullable()->after('client_location_id');
            $table->string('locale')->nullable()->after('timezone');
            $table->string('address_line1')->nullable()->after('locale');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city')->nullable()->after('address_line2');
            $table->string('state')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('state');
            $table->string('country')->nullable()->after('postal_code');
            $table->boolean('two_factor_enabled')->default(false)->after('country');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
            $table->string('otp_secret')->nullable()->after('two_factor_confirmed_at');
            $table->timestamp('email_changed_at')->nullable()->after('otp_secret');
            $table->timestamp('phone_changed_at')->nullable()->after('email_changed_at');
            $table->timestamp('password_changed_at')->nullable()->after('phone_changed_at');
            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');

            $table->index('type');
            $table->index('status');
            $table->index('client_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['client_location_id']);

            $table->dropConstrainedForeignId('client_location_id');
            $table->dropColumn([
                'type',
                'status',
                'phone',
                'first_name',
                'last_name',
                'date_of_birth',
                'timezone',
                'locale',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
                'country',
                'two_factor_enabled',
                'two_factor_confirmed_at',
                'otp_secret',
                'email_changed_at',
                'phone_changed_at',
                'password_changed_at',
                'last_login_at',
            ]);
        });
    }
};
