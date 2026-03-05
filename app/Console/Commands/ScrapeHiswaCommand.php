<?php

namespace App\Console\Commands;

use App\Services\HiswaScraperService;
use Illuminate\Console\Command;

class ScrapeHiswaCommand extends Command
{
    protected $signature = 'hiswa:scrape
                            {--limit= : Maximum number of harbors to scrape}
                            {--dry-run : Parse but do not save to database}';

    protected $description = 'Scrape HISWA jachthavens and upsert into the harbors table';

    public function handle(HiswaScraperService $scraper): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Starting HISWA jachthavens scrape...');

        if ($limit) {
            $this->info("  Limit: {$limit} harbors");
        }

        if ($dryRun) {
            $this->warn('  DRY RUN — no changes will be saved');
        }

        $results = $scraper->scrape($limit, $dryRun);

        $this->newLine();
        $this->info('✅ Scrape complete:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $results['created']],
                ['Updated', $results['updated']],
                ['Skipped', $results['skipped']],
                ['Errors', $results['errors']],
            ]
        );

        return $results['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
