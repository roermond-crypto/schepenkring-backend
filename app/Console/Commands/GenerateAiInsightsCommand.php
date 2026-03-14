<?php

namespace App\Console\Commands;

use App\Services\AiDailyInsightService;
use Illuminate\Console\Command;
use Throwable;

class GenerateAiInsightsCommand extends Command
{
    protected $signature = 'app:generate-ai-insights
        {--start= : ISO8601 datetime for the period start}
        {--end= : ISO8601 datetime for the period end}
        {--timezone= : Timezone used to interpret start/end values}';

    protected $description = 'Generate nightly AI engineering insights from audit and platform error summaries';

    public function handle(AiDailyInsightService $service): int
    {
        try {
            $insight = $service->generate([
                'start' => $this->option('start'),
                'end' => $this->option('end'),
                'timezone' => $this->option('timezone'),
            ]);
        } catch (Throwable $exception) {
            $this->error('AI insights generation failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'AI insights generated for %s -> %s [%s]',
            $insight->period_start?->toIso8601String() ?? 'n/a',
            $insight->period_end?->toIso8601String() ?? 'n/a',
            $insight->overall_status ?? $insight->status
        ));

        return self::SUCCESS;
    }
}
