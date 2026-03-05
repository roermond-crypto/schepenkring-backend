<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class IndexCatalogCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yachtshift:index-pinecone {--limit= : Limit the number to dispatch} {--force : Index all regardless of score}';
    protected $description = 'Dispatches IndexCatalogToPineconeJob for all high-quality pending boats.';

    public function handle()
    {
        $this->info("Starting Pinecone Index Queueing...");

        $query = \Illuminate\Support\Facades\DB::table('boat_catalog')
            ->where('pinecone_status', 'pending');

        if (!$this->option('force')) {
            // Only index decent quality records, skipping empty/worthless ones to save vector costs
            $query->where('quality_score', '>=', 70);
        }

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $boats = $query->get();

        if ($boats->isEmpty()) {
            $this->info("No pending high-quality boats to index.");
            return self::SUCCESS;
        }

        $this->info("Found {$boats->count()} boats to index. Dispatching jobs...");

        foreach ($boats as $boat) {
            \App\Jobs\IndexCatalogToPineconeJob::dispatch($boat->id)->onQueue('pinecone');
        }

        $this->info("Successfully dispatched {$boats->count()} jobs to the 'pinecone' queue.");
        return self::SUCCESS;
    }
}
