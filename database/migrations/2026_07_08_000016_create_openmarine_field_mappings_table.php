<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openmarine_field_mappings', function (Blueprint $table) {
            $table->id();
            // Yacht attribute path as read in OpenMarineService::buildXml(), e.g.
            // 'manufacturer', 'engine_manufacturer', 'images[].url'.
            $table->string('schepenkring_field', 150);
            // Dot-path into the generated <openmarine> XML, e.g.
            // 'boat.manufacturer', 'boat.engine.horsepower', 'boat.images.image.url'.
            $table->string('openmarine_xml_path', 200);
            $table->string('group_label', 80)->nullable();
            $table->boolean('is_required')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Explicit short name — the auto-generated name for this column
            // pair (73 chars) exceeds MySQL's 64-char identifier limit.
            $table->unique(['schepenkring_field', 'openmarine_xml_path'], 'openmarine_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openmarine_field_mappings');
    }
};
