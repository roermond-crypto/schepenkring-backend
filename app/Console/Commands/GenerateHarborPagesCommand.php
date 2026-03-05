<?php

namespace App\Console\Commands;

use App\Models\Harbor;
use App\Jobs\GenerateHarborPageJob;
use App\Services\HarborAiPageService;
use Illuminate\Console\Command;

class GenerateHarborPagesCommand extends Command
{
    protected $signature = 'harbors:generate-pages
                            {--limit= : Maximum number of harbors to process}
                            {--id= : Generate page for a specific harbor}
                            {--locale=nl : Locale for page generation}
                            {--force : Regenerate even if data has not changed}
                            {--sync : Run synchronously instead of queuing}';

    protected $description = 'Generate or regenerate AI harbor pages';

    public function handle(): int
    {
        $locale = $this->option('locale');

        if ($id = $this->option('id')) {
            $harbor = Harbor::findOrFail($id);
            $this->generateOne($harbor, $locale);
            return self::SUCCESS;
        }

        // Get harbors that have at least a place_id (enriched)
        $query = Harbor::whereNotNull('gmaps_place_id');

        if (!$this->option('force')) {
            // Only process harbors without pages or where source data changed
            $query->where(function ($q) use ($locale) {
                $q->doesntHave('pages')
                  ->orWhereHas('pages', function ($p) use ($locale) {
                      $p->where('locale', $locale);
                      // Will be filtered by source hash in the service
                  });
            });
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        if ($limit) {
            $query->limit($limit);
        }

        $harbors = $query->get();
        $this->info("📝 Generating pages for {$harbors->count()} harbor(s) in locale '{$locale}'...");

        $bar = $this->output->createProgressBar($harbors->count());

        foreach ($harbors as $harbor) {
            $this->generateOne($harbor, $locale);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ Page generation complete!');

        return self::SUCCESS;
    }

    private function generateOne(Harbor $harbor, string $locale): void
    {
        if ($this->option('sync')) {
            $service = app(HarborAiPageService::class);
            $result = $service->generatePage($harbor, $locale);

            if (isset($result['error'])) {
                $this->error("  ✗ {$harbor->name}: {$result['error']}");
            } elseif (isset($result['skipped'])) {
                $this->line("  ⏭ {$harbor->name}: no changes");
            } else {
                $this->info("  ✓ {$harbor->name}: page generated");
            }
        } else {
            GenerateHarborPageJob::dispatch($harbor, $locale);
            $this->line("  ⏳ {$harbor->name}: queued");
        }
    }
}
