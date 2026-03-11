<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\CopilotLearningService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IngestAuditLogLearningJob
{
    use Dispatchable, SerializesModels;

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
