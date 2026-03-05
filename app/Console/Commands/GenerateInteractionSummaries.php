<?php

namespace App\Console\Commands;

use App\Models\InteractionTimelineEntry;
use App\Services\InteractionSummaryService;
use Illuminate\Console\Command;

class GenerateInteractionSummaries extends Command
{
    protected $signature = 'interaction:daily-summary {--days=1}';
    protected $description = 'Generate daily interaction summaries per user.';

    public function handle(InteractionSummaryService $service): int
    {
        $days = (int) $this->option('days');

        $userIds = InteractionTimelineEntry::query()
            ->whereNotNull('user_id')
            ->where('occurred_at', '>=', now()->subDays($days))
            ->distinct()
            ->pluck('user_id');

        $count = 0;
        foreach ($userIds as $userId) {
            $summary = $service->buildSummary((int) $userId, $days);
            if ($summary) {
                $count++;
            }
        }

        $this->info('Interaction summaries updated: ' . $count);

        return self::SUCCESS;
    }
}
