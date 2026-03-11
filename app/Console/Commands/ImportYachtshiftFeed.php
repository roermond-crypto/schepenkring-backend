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
    protected $signature = 'app:import-yachtshift 
                            {url? : The YachtShift XML feed URL}
                            {--location_id= : The ID of the location to associate with these boats}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import boats from a YachtShift XML feed (optionally for a specific location)';

    /**
     * Execute the console command.
     */
    public function handle(YachtshiftImportService $importService)
    {
        $url = $this->argument('url');
        $locationId = $this->option('location_id');

        // If no URL provided, we can loop through known feeds or use a default
        $feeds = $url ? [$url] : [
            config('services.yachtshift.feed_url_1', 'https://krekelberg.yachtshift.nl/yachtshift/export/feed/key/790b0db72e79d4f9f461b469a6b75c1249'),
            // Add more default feeds here if available, e.g.:
            // config('services.yachtshift.feed_url_2'),
        ];

        foreach ($feeds as $feedUrl) {
            $this->info("Starting YachtShift import from: {$feedUrl}");
            if ($locationId) {
                $this->info("Mapping to Location ID: {$locationId}");
            }

            $result = $importService->importFromUrl($feedUrl, $locationId ? (int) $locationId : null);

            if ($result['success'] ?? false) {
                $this->info("Import from {$feedUrl} completed successfully!");
                $this->table(
                    ['Imported', 'Updated', 'Errors'],
                    [[$result['imported'], $result['updated'], $result['errors']]]
                );
            } else {
                $this->error("Import from {$feedUrl} failed: " . ($result['message'] ?? 'Unknown error'));
            }
        }

        return 0;
    }
}
