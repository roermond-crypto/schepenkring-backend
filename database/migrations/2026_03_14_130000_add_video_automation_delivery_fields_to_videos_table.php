<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            if (! Schema::hasColumn('videos', 'generation_trigger')) {
                $table->string('generation_trigger')->nullable()->after('template_type');
            }

            if (! Schema::hasColumn('videos', 'whatsapp_status')) {
                $table->string('whatsapp_status')->nullable()->after('generated_at');
            }

            if (! Schema::hasColumn('videos', 'whatsapp_sent_at')) {
                $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_status');
            }

            if (! Schema::hasColumn('videos', 'whatsapp_message_id')) {
                $table->string('whatsapp_message_id')->nullable()->after('whatsapp_sent_at');
            }

            if (! Schema::hasColumn('videos', 'whatsapp_recipient')) {
                $table->string('whatsapp_recipient')->nullable()->after('whatsapp_message_id');
            }

            if (! Schema::hasColumn('videos', 'whatsapp_error')) {
                $table->text('whatsapp_error')->nullable()->after('whatsapp_recipient');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $columns = [
                'generation_trigger',
                'whatsapp_status',
                'whatsapp_sent_at',
                'whatsapp_message_id',
                'whatsapp_recipient',
                'whatsapp_error',
            ];

            $existing = array_values(array_filter($columns, static fn (string $column) => Schema::hasColumn('videos', $column)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
