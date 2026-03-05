<?php

namespace App\Console\Commands;

use App\Jobs\CreateAutomatedTaskJob;
use App\Models\TaskAutomation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessTaskAutomations extends Command
{
    protected $signature = 'tasks:process-automations';
    protected $description = 'Dispatch due automated tasks for processing';

    public function handle(): int
    {
        $now = now();

        $due = TaskAutomation::where('status', 'pending')
            ->where('due_at', '<=', $now)
            ->orderBy('due_at')
            ->limit(200)
            ->get();

        foreach ($due as $automation) {
            $updated = TaskAutomation::where('id', $automation->id)
                ->where('status', 'pending')
                ->update(['status' => 'processing']);

            if ($updated) {
                CreateAutomatedTaskJob::dispatch($automation->id);
            }
        }

        return Command::SUCCESS;
    }
}
