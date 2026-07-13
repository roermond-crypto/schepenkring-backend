<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openmarine_field_mapping_versions', function (Blueprint $table) {
            $table->id();
            // The whole mapping table is versioned as one unit (a single
            // mapping row change affects the overall export shape), unlike
            // ContractTemplateVersion which versions one template's content —
            // there's no natural "parent" row to attach a version counter to,
            // so the next version number is MAX(version)+1 across this table.
            $table->unsignedBigInteger('version');
            $table->json('mappings_snapshot');
            $table->string('change_note', 500)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->unique('version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openmarine_field_mapping_versions');
    }
};
