<?php

namespace App\Console\Commands;

use App\Http\Controllers\BoatRecognitionController;
use App\Models\Yacht;
use Illuminate\Console\Command;

class BackfillYachtEmbeddings extends Command
{
    protected $signature = 'yachts:backfill-embeddings {--force : Process all yachts even if they already have embeddings}';
    protected $description = 'Generate image embeddings for all existing yachts that have a main image. Required for boat recognition.';

    public function handle(): int
    {
        $query = Yacht::whereNotNull('main_image')->where('main_image', '!=', '');

        if (!$this->option('force')) {
            // Only process yachts without an existing embedding
            $query->whereDoesntHave('embeddings', function ($q) {
                $q->where('is_main_image', true);
            });
        }

        $yachts = $query->get();

        if ($yachts->isEmpty()) {
            $this->info('All yachts already have embeddings. Use --force to regenerate.');
            return 0;
        }

        $this->info("Processing {$yachts->count()} yacht(s)...");
        $bar = $this->output->createProgressBar($yachts->count());
        $bar->start();

        $controller = app(BoatRecognitionController::class);
        $success = 0;
        $failed = 0;

        foreach ($yachts as $yacht) {
            try {
                $controller->generateEmbeddingForYacht($yacht);
                $success++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Failed: {$yacht->boat_name} (ID: {$yacht->id}) — {$e->getMessage()}");
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done! Success: {$success}, Failed: {$failed}");

        return 0;
    }
}
