<?php

namespace App\Console\Commands;

use App\Services\BoatFieldObservationBackfillService;
use Illuminate\Console\Command;

class BackfillBoatFieldObservations extends Command
{
    protected $signature = 'app:backfill-boat-field-observations
        {--reset : Clear existing observation rows for the selected sources before rebuilding them}
        {--source=* : Limit backfill to one or more sources (yachtshift, scrape, future_import)}';

    protected $description = 'Backfill boat field observation frequencies from existing imported yacht data.';

    public function handle(BoatFieldObservationBackfillService $service): int
    {
        $summary = $service->backfill(
            reset: (bool) $this->option('reset'),
            sources: $this->option('source') ?: null,
        );

        $this->info('Boat field observation backfill completed.');
        $this->line('Sources: ' . implode(', ', $summary['sources']));
        $this->line('Fields processed: ' . $summary['fields_processed']);
        $this->line('Observation rows written: ' . $summary['observations_written']);

        foreach ($summary['written_by_source'] as $source => $count) {
            $this->line(sprintf(' - %s: %d', $source, $count));
        }

        return self::SUCCESS;
    }
}
