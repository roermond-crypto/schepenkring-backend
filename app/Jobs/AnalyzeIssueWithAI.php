<?php

namespace App\Jobs;

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
        $prompt = <<<PROMPT
You are a support assistant for Schepenkring, a Dutch boat marketplace.

Analyze the following user-reported issue and provide:
1. A short diagnosis (1-2 sentences)
2. Suggested next steps for the support team
3. Severity: low / medium / high

Issue title: {$this->issue->title}
Description: {$this->issue->description}
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
            $this->fail($response->toException());
            return;
        }

        $analysis = $response->json('choices.0.message.content', 'Analysis unavailable.');

        $this->issue->update([
            'status' => 'ai_analyzed',
            'ai_analysis' => $analysis,
            'ai_analyzed_at' => now(),
        ]);
    }
}
