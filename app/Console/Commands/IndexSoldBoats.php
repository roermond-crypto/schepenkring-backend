<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Yacht;
use App\Models\ScrapeRun;
use App\Services\YachtEnrichmentService;
use App\Services\PineconeMatcherService;
use Illuminate\Console\Command;

class IndexSoldBoats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:index-sold-boats
        {--id= : Process a single yacht ID}
        {--limit= : Limit number of yachts to process}
        {--scrape-run-id= : Require this scrape run to pass the completeness gate}
        {--min-completeness=0.98 : Required scrape completeness before full indexing}
        {--force : Bypass the scrape completeness gate}
        {--rebuild : Delete existing Pinecone yacht vectors before indexing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich and index sold boats from the archive into Pinecone';

    /**
     * Execute the console command.
     */
    public function handle(YachtEnrichmentService $enrichmentService, PineconeMatcherService $pineconeService)
    {
        $yachtId = $this->option('id');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $minimumCompleteness = (float) $this->option('min-completeness');

        if (! $yachtId && ! $this->option('force') && ! $this->hasPassingScrapeRun($minimumCompleteness)) {
            $this->error('Latest Schepenkring scrape did not pass the completeness gate. Re-run scraper or pass --force.');

            return 1;
        }

        $isRebuild = (bool) $this->option('rebuild');

        if ($isRebuild) {
            AuditLog::create([
                'action' => 'pinecone.rebuild_started',
                'category' => 'pinecone',
                'risk_level' => 'medium',
                'result' => 'success',
                'meta' => ['forced' => (bool) $this->option('force')],
            ]);

            if (! $pineconeService->deleteAllYachtVectors()) {
                $this->error('Failed to delete old Pinecone vectors. Indexing stopped to avoid mixing stale and fresh data.');

                AuditLog::create([
                    'action' => 'pinecone.rebuild_completed',
                    'category' => 'pinecone',
                    'risk_level' => 'high',
                    'result' => 'fail',
                    'meta' => ['reason' => 'Failed to delete existing vectors'],
                ]);

                return 1;
            }
        } else {
            AuditLog::create([
                'action' => 'pinecone.sync_started',
                'category' => 'pinecone',
                'risk_level' => 'low',
                'result' => 'success',
                'meta' => ['yacht_id' => $yachtId, 'limit' => $limit],
            ]);
        }

        if ($yachtId) {
            $yachts = Yacht::where('id', $yachtId)->get();
        } else {
            $yachts = Yacht::where('status', 'sold')
                ->where('source', 'schepenkring_sold_archive')
                ->when($limit !== null, fn ($query) => $query->limit($limit))
                ->get();
        }

        if ($yachts->isEmpty()) {
            $this->info('No boats to process.');
            return 0;
        }

        $this->info("Processing " . $yachts->count() . " boats...");

        $indexed = 0;
        $failed = 0;

        foreach ($yachts as $yacht) {
            $this->comment("Enriching boat: {$yacht->boat_name} (ID: {$yacht->id})");

            if ($enrichmentService->enrich($yacht)) {
                $this->info("Enrichment successful. Indexing in Pinecone...");

                if ($pineconeService->upsertYacht($yacht)) {
                    $this->info("Indexing successful.");
                    $indexed++;
                } else {
                    $this->error("Indexing failed.");
                    $failed++;
                }
            } else {
                $this->error("Enrichment failed.");
                $failed++;
            }
        }

        AuditLog::create([
            'action' => $isRebuild ? 'pinecone.rebuild_completed' : 'pinecone.sync_completed',
            'category' => 'pinecone',
            'risk_level' => 'low',
            'result' => $failed > 0 ? 'fail' : 'success',
            'meta' => [
                'processed' => $yachts->count(),
                'indexed' => $indexed,
                'failed' => $failed,
            ],
        ]);

        $this->info('Done.');
        return 0;
    }

    private function hasPassingScrapeRun(float $minimumCompleteness): bool
    {
        $run = $this->option('scrape-run-id')
            ? ScrapeRun::query()->find((int) $this->option('scrape-run-id'))
            : ScrapeRun::query()
                ->where('source', 'schepenkring_sold_archive')
                ->latest('started_at')
                ->first();

        return $run?->passedCompletenessGate($minimumCompleteness) ?? false;
    }
}
