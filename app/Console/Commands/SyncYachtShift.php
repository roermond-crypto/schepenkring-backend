<?php

namespace App\Console\Commands;

use App\Services\YachtShiftSyncService;
use Illuminate\Console\Command;

class SyncYachtShift extends Command
{
    protected $signature = 'yachtshift:sync
                            {--direction=both : import, export, or both}
                            {--dry-run : Run without writing local or remote changes}';

    protected $description = 'Synchronize YachtShift listings with Schepenkring boats.';

    public function handle(YachtShiftSyncService $sync): int
    {
        $direction = strtolower((string) $this->option('direction'));
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($direction, ['import', 'export', 'both'], true)) {
            $this->error('Direction must be import, export, or both.');

            return self::FAILURE;
        }

        try {
            $result = [
                'direction' => $direction,
                'dry_run' => $dryRun,
            ];

            if ($direction === 'import' || $direction === 'both') {
                $result['import'] = $sync->import($dryRun);
            }

            if ($direction === 'export' || $direction === 'both') {
                $result['export'] = $sync->export($dryRun);
            }
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
