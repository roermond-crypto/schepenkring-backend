<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\CopilotLearningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestAuditLogLearningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private readonly int $auditLogId
    ) {
    }

    public function handle(CopilotLearningService $learning): void
    {
        $log = AuditLog::find($this->auditLogId);

        if (! $log) {
            return;
        }

        $learning->ingestAuditLog($log);
    }
}
