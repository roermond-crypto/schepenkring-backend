<?php

namespace App\Console\Commands;

use App\Jobs\EnrichHarborThirdPartyJob;
use App\Models\Harbor;
use Illuminate\Console\Command;

class EnrichHarborsThirdPartyCommand extends Command
{
    protected $signature = 'harbors:enrich-third-party
                            {--limit=100 : Maximum number of harbors}
                            {--sync : Run synchronously}';

    protected $description = 'Optional third-party enrichment for harbors with missing contacts';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $harbors = Harbor::missingContacts()
            ->whereNotNull('gmaps_place_id')
            ->limit($limit)
            ->get();

        $this->info("Third-party enrichment for {$harbors->count()} harbor(s)");

        foreach ($harbors as $harbor) {
            if ($this->option('sync')) {
                $job = new EnrichHarborThirdPartyJob($harbor);
                $job->handle(app(\App\Services\OutscraperEnrichmentService::class));
            } else {
                EnrichHarborThirdPartyJob::dispatch($harbor);
            }
        }

        return self::SUCCESS;
    }
}
