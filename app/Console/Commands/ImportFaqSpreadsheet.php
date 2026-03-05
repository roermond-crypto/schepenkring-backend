<?php

namespace App\Console\Commands;

use App\Services\FaqImportService;
use Illuminate\Console\Command;

class ImportFaqSpreadsheet extends Command
{
    protected $signature = 'faq:import {file} {--language=nl} {--generate-long-descriptions}';
    protected $description = 'Import FAQ spreadsheet into faq and faq_translation tables';

    public function handle(FaqImportService $importer): int
    {
        $file = $this->argument('file');
        if (!is_string($file) || !file_exists($file)) {
            $this->error('File not found: ' . $file);
            return self::FAILURE;
        }

        $result = $importer->import($file, [
            'default_language' => $this->option('language') ?: 'nl',
            'generate_long_descriptions' => (bool) $this->option('generate-long-descriptions'),
        ]);

        $this->info('FAQ import complete');
        $this->table(['Imported', 'Updated', 'Skipped'], [[
            $result['imported'] ?? 0,
            $result['updated'] ?? 0,
            $result['skipped'] ?? 0,
        ]]);

        return self::SUCCESS;
    }
}
