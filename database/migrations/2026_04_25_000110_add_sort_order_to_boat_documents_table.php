<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('boat_documents', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('document_type');
                $table->index(['boat_id', 'document_type', 'sort_order'], 'boat_docs_type_sort_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('boat_documents', function (Blueprint $table) {
            if (Schema::hasColumn('boat_documents', 'sort_order')) {
                $table->dropIndex('boat_docs_type_sort_idx');
                $table->dropColumn('sort_order');
            }
        });
    }
};
