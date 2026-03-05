<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_document_id')
                ->constrained('invoice_documents')
                ->restrictOnDelete();
            $table->string('status')->index();
            $table->string('action')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_status_histories');
    }
};
