<?php

namespace App\Console\Commands;

use App\Models\Harbor;
use App\Jobs\EnrichHarborJob;
use Illuminate\Console\Command;

class EnrichHarborsCommand extends Command
{
    protected $signature = 'harbors:enrich
                            {--limit= : Maximum number of harbors to enrich}
                            {--id= : Enrich a specific harbor by ID}
                            {--force : Re-enrich even if already geocoded}
                            {--sync : Run synchronously instead of queuing}';

    protected $description = 'Enrich harbors with Google geocoding and place details';

    public function handle(): int
    {
        if ($id = $this->option('id')) {
            $harbor = Harbor::findOrFail($id);
            $this->enrichOne($harbor);
            return self::SUCCESS;
        }

        $query = Harbor::query();

        if (!$this->option('force')) {
            $query->where(function ($q) {
                $q->needsGeocode()
                  ->orWhere(function ($q2) {
                      $q2->needsPlaceDetails();
                  })
                  ->orWhere(function ($q3) {
                      $q3->needsPlacePhotos();
                  });
            });
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        if ($limit) {
            $query->limit($limit);
        }

        $harbors = $query->get();
        $this->info("🌍 Enriching {$harbors->count()} harbor(s)...");

        $bar = $this->output->createProgressBar($harbors->count());

        foreach ($harbors as $harbor) {
            $this->enrichOne($harbor);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ Enrichment complete!');

        return self::SUCCESS;
    }

    private function enrichOne(Harbor $harbor): void
    {
        if ($this->option('sync')) {
            // Run synchronously
            $job = new EnrichHarborJob($harbor);
            $job->handle(
                app(\App\Services\GoogleGeocodingService::class),
                app(\App\Services\GooglePlaceDetailsService::class)
            );
            $this->line("  Enriched: {$harbor->name} → place_id: {$harbor->fresh()->gmaps_place_id}");
        } else {
            EnrichHarborJob::dispatch($harbor);
            $this->line("  Queued: {$harbor->name}");
        }
    }
}
