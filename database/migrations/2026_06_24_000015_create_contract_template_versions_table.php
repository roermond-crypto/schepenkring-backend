<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_id')->constrained('contract_templates')->cascadeOnDelete();
            $table->unsignedBigInteger('version');
            $table->longText('content_html');
            $table->json('content_json')->nullable();
            $table->string('change_note')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['contract_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_template_versions');
    }
};
