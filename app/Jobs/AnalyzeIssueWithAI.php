<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeIssueWithAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public Issue $issue)
    {
    }

    public function handle(): void
    {
        $this->issue->update([
            'status' => 'ai_pending',
            'ai_status' => 'processing',
            'ai_error' => null,
        ]);
        $this->audit('issue.ai_started');

        $logs = json_encode($this->issue->logs ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
You are a support assistant for Schepenkring, a Dutch boat marketplace.

Analyze the following user-reported issue and provide:
1. A short diagnosis (1-2 sentences)
2. Suggested next steps for the support team
3. Priority: low / medium / high

Return compact JSON with keys: summary, priority, suggested_fix.

Issue title: {$this->issue->title}
Description: {$this->issue->description}
Page URL: {$this->issue->page_url}
Browser: {$this->issue->browser}
Device: {$this->issue->device}
User role: {$this->issue->user?->role}
Screenshot path: {$this->issue->screenshot_path}
Frontend logs: {$logs}
PROMPT;

        $response = Http::withToken(config('services.openai.api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a concise technical support analyst.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 400,
            ]);

        if ($response->failed()) {
            Log::error('AnalyzeIssueWithAI: OpenAI request failed', [
                'issue_id' => $this->issue->id,
                'status' => $response->status(),
            ]);
            $this->markFailed('OpenAI request failed: ' . $response->status());
            $this->fail($response->toException());
            return;
        }

        $analysis = $response->json('choices.0.message.content', 'Analysis unavailable.');
        $parsed = $this->parseAnalysis($analysis);

        $this->issue->update([
            'status' => 'ai_completed',
            'ai_status' => 'completed',
            'ai_analysis' => $analysis,
            'ai_summary' => $parsed['summary'],
            'ai_priority' => $parsed['priority'],
            'ai_suggested_fix' => $parsed['suggested_fix'],
            'ai_error' => null,
            'ai_analyzed_at' => now(),
        ]);
        $this->audit('issue.ai_completed', [
            'ai_priority' => $parsed['priority'],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    private function parseAnalysis(string $analysis): array
    {
        $decoded = json_decode($analysis, true);

        if (is_array($decoded)) {
            return [
                'summary' => (string) ($decoded['summary'] ?? $analysis),
                'priority' => $this->normalizePriority($decoded['priority'] ?? null),
                'suggested_fix' => (string) ($decoded['suggested_fix'] ?? ''),
            ];
        }

        return [
            'summary' => $analysis,
            'priority' => 'medium',
            'suggested_fix' => '',
        ];
    }

    private function normalizePriority(mixed $priority): string
    {
        $priority = strtolower((string) $priority);

        return in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium';
    }

    private function markFailed(string $message): void
    {
        $this->issue->update([
            'status' => 'failed',
            'ai_status' => 'failed',
            'ai_error' => $message,
        ]);
        $this->audit('issue.ai_failed', ['error' => $message], 'FAILURE');
    }

    private function audit(string $action, array $meta = [], string $result = 'SUCCESS'): void
    {
        AuditLog::create([
            'action' => $action,
            'risk_level' => $result === 'SUCCESS' ? 'low' : 'medium',
            'result' => $result,
            'target_type' => Issue::class,
            'target_id' => $this->issue->id,
            'entity_type' => Issue::class,
            'entity_id' => $this->issue->id,
            'meta' => $meta,
        ]);
    }
}
