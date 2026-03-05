<?php

namespace App\Console\Commands;

use App\Models\YachtImage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupTempOriginals extends Command
{
    protected $signature = 'app:cleanup-temp-originals
                            {--hours=48 : Delete temp originals older than this many hours}
                            {--dry-run : Only show what would be deleted without actually deleting}';

    protected $description = 'Clean up temporary original images that are no longer needed.';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');
        $cutoff = Carbon::now()->subHours($hours);

        $this->info("Cleaning up temp originals older than {$hours} hours (before {$cutoff})...");

        if ($dryRun) {
            $this->warn('DRY RUN — no files will be deleted.');
        }

        // 1. Clean up DB-tracked images where:
        //    - status is 'approved'
        //    - keep_original is false
        //    - original_temp_url is not null
        //    - created more than X hours ago
        $images = YachtImage::where('status', 'approved')
            ->where('keep_original', false)
            ->whereNotNull('original_temp_url')
            ->where('created_at', '<', $cutoff)
            ->get();

        $deletedCount = 0;

        foreach ($images as $image) {
            $path = $image->original_temp_url;

            if (Storage::disk('public')->exists($path)) {
                if (!$dryRun) {
                    Storage::disk('public')->delete($path);
                    $image->update(['original_temp_url' => null]);
                }
                $deletedCount++;
                $this->line("  Deleted: {$path}");
            } else {
                // File already gone, just clear the DB reference
                if (!$dryRun) {
                    $image->update(['original_temp_url' => null]);
                }
            }
        }

        // 2. Clean up orphaned files in original_temp/ that have no DB record
        $tempDirs = Storage::disk('public')->directories('original_temp');

        foreach ($tempDirs as $dir) {
            $files = Storage::disk('public')->files($dir);

            foreach ($files as $file) {
                $lastModified = Storage::disk('public')->lastModified($file);
                $fileTime = Carbon::createFromTimestamp($lastModified);

                if ($fileTime->lt($cutoff)) {
                    // Check if any DB record references this file
                    $hasRecord = YachtImage::where('original_temp_url', $file)->exists();

                    if (!$hasRecord) {
                        if (!$dryRun) {
                            Storage::disk('public')->delete($file);
                        }
                        $deletedCount++;
                        $this->line("  Orphaned: {$file}");
                    }
                }
            }
        }

        $action = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$action} {$deletedCount} temp original files.");

        Log::info("CleanupTempOriginals: {$action} {$deletedCount} files (cutoff: {$cutoff})");

        return Command::SUCCESS;
    }
}
