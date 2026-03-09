<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YachtshiftImportService;

class ImportYachtshiftFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-yachtshift {url? : The YachtShift XML feed URL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import available boats from a YachtShift XML feed';

    /**
     * Execute the console command.
     */
    public function handle(YachtshiftImportService $importService)
    {
        $url = $this->argument('url') ?? config('services.yachtshift.feed_url_1', 'https://krekelberg.yachtshift.nl/yachtshift/export/feed/key/790b0db72e79d4f9f461b469a6b75c1249');

        $this->info("Starting YachtShift import from: {$url}");

        $result = $importService->importFromUrl($url);

        if ($result['success'] ?? false) {
            $this->info("Import completed successfully!");
            $this->table(
                ['Imported', 'Updated', 'Errors'],
                [[$result['imported'], $result['updated'], $result['errors']]]
            );
        } else {
            $this->error("Import failed: " . ($result['message'] ?? 'Unknown error'));
        }

        return 0;
    }
}
