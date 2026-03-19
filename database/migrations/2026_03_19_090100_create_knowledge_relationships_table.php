<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_entity_id')->constrained('knowledge_entities')->cascadeOnDelete();
            $table->foreignId('to_entity_id')->constrained('knowledge_entities')->cascadeOnDelete();
            $table->string('relationship_type', 60)->index();
            $table->decimal('weight', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['from_entity_id', 'to_entity_id', 'relationship_type'],
                'knowledge_relationships_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_relationships');
    }
};
