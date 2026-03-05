<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToYachtsTable extends Migration
{
    public function up()
    {
        Schema::table('yachts', function (Blueprint $table) {
            // boat_name – if you use 'name' instead, you don't need this
            if (!Schema::hasColumn('yachts', 'boat_name')) {
                $table->string('boat_name')->nullable()->after('name');
            }

            // vessel_id – unique identifier for the yacht
            if (!Schema::hasColumn('yachts', 'vessel_id')) {
                $table->string('vessel_id')->nullable()->unique()->after('id');
            }

            // main_image – single hero image
            if (!Schema::hasColumn('yachts', 'main_image')) {
                $table->string('main_image')->nullable()->after('description');
            }

            // current_bid – the highest active bid
            if (!Schema::hasColumn('yachts', 'current_bid')) {
                $table->decimal('current_bid', 15, 2)->nullable()->after('price');
            }

            // If you don't have a 'price' column, add it too
            if (!Schema::hasColumn('yachts', 'price')) {
                $table->decimal('price', 15, 2)->nullable()->after('year');
            }

            // status – For Sale / For Bid / Sold / Draft
            if (!Schema::hasColumn('yachts', 'status')) {
                $table->string('status')->default('Draft')->after('price');
            }
        });
    }

    public function down()
    {
        Schema::table('yachts', function (Blueprint $table) {
            $columns = ['boat_name', 'vessel_id', 'main_image', 'current_bid', 'price', 'status'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('yachts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}