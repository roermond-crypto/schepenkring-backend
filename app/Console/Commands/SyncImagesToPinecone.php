<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;

class SyncImagesToPinecone extends Command
{
    protected $signature = 'pinecone:sync';
    protected $description = 'Sync all boat images from local storage to Pinecone (skips existing)';

    public function handle()
    {
        $directory = storage_path('app/public/boats');
        if (!File::exists($directory)) {
            $this->error("Directory not found: $directory");
            return;
        }

        $files = File::files($directory);
        $this->info("Found " . count($files) . " images. Starting sync...");

        // Determine Python path (Local Windows vs VPS Virtual Env)
        $pythonPath = (PHP_OS_FAMILY === 'Windows') ? 'python' : base_path('venv/bin/python');

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $fullPath = $file->getRealPath();
            $publicUrl = asset('storage/boats/' . $filename);

            $this->comment("Checking: $filename");

            $process = new Process([
                $pythonPath,
                app_path('Scripts/pinecone_sync.py'),
                env('GEMINI_API_KEY'),
                env('PINECONE_API_KEY'),
                env('PINECONE_INDEX'),
                $fullPath,
                $publicUrl
            ]);

            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful()) {
                $output = trim($process->getOutput());
                if (str_contains($output, 'SKIPPED')) {
                    $this->line(" - Already indexed. Skipped.");
                } else {
                    $this->info(" - Successfully synced!");
                }
            } else {
                $this->error(" - Failed: " . $process->getErrorOutput());
            }
        }

        $this->info("Sync process complete.");
    }
}