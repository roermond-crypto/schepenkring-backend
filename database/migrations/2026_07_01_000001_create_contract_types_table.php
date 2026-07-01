<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // Human label, e.g. "Koopovereenkomst"
            $table->string('slug')->unique(); // Machine key, e.g. "purchase_contract"
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default types from the existing hardcoded constant
        $defaults = [
            ['purchase_contract',   'Koopovereenkomst',             1],
            ['seller_assignment',   'Verkoopopdracht',              2],
            ['viewing_agreement',   'Bezichtigingsovereenkomst',    3],
            ['offer_agreement',     'Bodovereenkomst',              4],
            ['delivery_document',   'Leveringsdocument',            5],
            ['brokerage_agreement', 'Makelaarsovereenkomst',        6],
        ];

        foreach ($defaults as [$slug, $name, $order]) {
            DB::table('contract_types')->insert([
                'name'        => $name,
                'slug'        => $slug,
                'is_active'   => true,
                'sort_order'  => $order,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_types');
    }
};
