<?php

namespace App\Console\Commands;

use App\Services\CopilotLearningService;
use Illuminate\Console\Command;

class CopilotMineSuggestions extends Command
{
    protected $signature = 'copilot:mine-suggestions {--days=30} {--auto-create}';

    protected $description = 'Mine audit and copilot history to generate copilot action suggestions.';

    public function handle(CopilotLearningService $learning): int
    {
        $result = $learning->mineFromHistory(
            (int) $this->option('days'),
            (bool) $this->option('auto-create')
        );

        $this->info('Suggestions generated: ' . $result['count']);

        return self::SUCCESS;
    }
}
