<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contract_instances')) Schema::create('contract_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sign_request_id')->nullable()->constrained('sign_requests')->nullOnDelete();
            $table->foreignId('contract_template_id')->nullable()->constrained('contract_templates')->nullOnDelete();
            $table->unsignedBigInteger('template_version_used')->nullable();
            $table->longText('content_html');          // editable per-instance content (starts as copy of template)
            $table->json('content_json')->nullable();   // TipTap JSON
            $table->longText('rendered_html')->nullable();  // tag-replaced version for display/PDF
            $table->unsignedBigInteger('yacht_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->json('tags_data')->nullable();       // the tag values used at generation time
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->unique('sign_request_id');
            $table->index(['yacht_id']);
            $table->index(['contract_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_instances');
    }
};
