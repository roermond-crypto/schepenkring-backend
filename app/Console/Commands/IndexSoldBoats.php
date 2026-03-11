<?php

namespace App\Console\Commands;

use App\Models\Yacht;
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
    protected $signature = 'app:index-sold-boats {--id= : Process a single yacht ID} {--limit=50 : Limit number of yachts to process}';

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
        $limit = (int) $this->option('limit');

        if ($yachtId) {
            $yachts = Yacht::where('id', $yachtId)->get();
        } else {
            // Process sold boats that haven't been successfully indexed yet
            // (We could use a flag, but for now we'll just process those from schepenkring_sold_archive)
            $yachts = Yacht::where('status', 'sold')
                ->where('source', 'schepenkring_sold_archive')
                // For safety, we can filter by lack of owners_comment (where AI summary is stored)
                ->whereNull('owners_comment') 
                ->limit($limit)
                ->get();
        }

        if ($yachts->isEmpty()) {
            $this->info('No boats to process.');
            return 0;
        }

        $this->info("Processing " . $yachts->count() . " boats...");

        foreach ($yachts as $yacht) {
            $this->comment("Enriching boat: {$yacht->boat_name} (ID: {$yacht->id})");
            
            if ($enrichmentService->enrich($yacht)) {
                $this->info("Enrichment successful. Indexing in Pinecone...");
                
                if ($pineconeService->upsertYacht($yacht)) {
                    $this->info("Indexing successful.");
                } else {
                    $this->error("Indexing failed.");
                }
            } else {
                $this->error("Enrichment failed.");
            }
        }

        $this->info('Done.');
        return 0;
    }
}
