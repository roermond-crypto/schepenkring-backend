<?php

namespace App\Services;

use App\Models\PlatformError;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;

class SentryIssueService
{
    public function upsertFromWebhook(array $payload): PlatformError
    {
        $issue = Arr::get($payload, 'data.issue') ?? Arr::get($payload, 'issue') ?? [];
        $event = Arr::get($payload, 'data.event') ?? Arr::get($payload, 'event') ?? [];

        $sentryId = (string) (Arr::get($issue, 'id') ?? Arr::get($payload, 'id'));
        $title = Arr::get($issue, 'title') ?? Arr::get($event, 'title') ?? Arr::get($event, 'message');
        $message = Arr::get($event, 'message') ?? Arr::get($issue, 'metadata.value') ?? $title;
        $level = Arr::get($issue, 'level') ?? Arr::get($event, 'level') ?? 'error';
        $status = Arr::get($issue, 'status') ?? Arr::get($payload, 'action') ?? 'unresolved';
        $project = Arr::get($payload, 'project') ?? Arr::get($issue, 'project.slug') ?? Arr::get($event, 'project');
        $environment = Arr::get($event, 'environment') ?? Arr::get($issue, 'environment') ?? Arr::get($payload, 'environment');
        $release = Arr::get($event, 'release') ?? Arr::get($issue, 'release') ?? Arr::get($payload, 'release');
        $url = Arr::get($issue, 'permalink');
        $route = Arr::get($event, 'request.url') ?? Arr::get($event, 'request');
        $source = $this->inferSource($event, $project);

        $count = (int) (Arr::get($issue, 'count') ?? 0);
        $userCount = (int) (Arr::get($issue, 'userCount') ?? 0);

        $firstSeen = Arr::get($issue, 'firstSeen') ? Carbon::parse(Arr::get($issue, 'firstSeen')) : null;
        $lastSeen = Arr::get($issue, 'lastSeen') ? Carbon::parse(Arr::get($issue, 'lastSeen')) : null;

        $tags = $this->normalizeTags(Arr::get($event, 'tags', []));
        $event = $this->sanitizeEvent($event);

        $error = PlatformError::firstOrNew([
            'sentry_issue_id' => $sentryId ?: null,
        ]);

        $error->fill([
            'title' => $title,
            'message' => $message,
            'level' => $level,
            'project' => $project,
            'environment' => $environment,
            'release' => $release,
            'source' => $source,
            'route' => is_string($route) ? $route : null,
            'url' => $url,
            'occurrences_count' => $count ?: $error->occurrences_count,
            'users_affected' => $userCount ?: $error->users_affected,
            'first_seen_at' => $firstSeen ?: $error->first_seen_at,
            'last_seen_at' => $lastSeen ?: now(),
            'status' => $this->normalizeStatus($status),
            'tags' => $tags ?: $error->tags,
            'last_event_sample_json' => $event ?: $error->last_event_sample_json,
        ]);

        $isNew = !$error->exists;
        $releaseChanged = $release && $error->isDirty('release');

        $error->save();

        if ($isNew || $releaseChanged || !$error->ai_dev_summary) {
            $this->generateAiSummary($error, $event);
        }

        return $error;
    }

    private function normalizeTags(array $tags): array
    {
        $out = [];
        foreach ($tags as $tag) {
            if (is_array($tag) && count($tag) >= 2) {
                $out[$tag[0]] = $tag[1];
            }
        }
        return $out;
    }

    private function sanitizeEvent(array $event): array
    {
        $redactKeys = ['authorization', 'cookie', 'set-cookie', 'password', 'token', 'api_key', 'secret'];
        array_walk_recursive($event, function (&$value, $key) use ($redactKeys) {
            foreach ($redactKeys as $needle) {
                if (stripos((string) $key, $needle) !== false) {
                    $value = '[REDACTED]';
                    return;
                }
            }
        });
        return $event;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);
        if (in_array($status, ['resolved', 'ignored'])) {
            return $status;
        }
        return 'unresolved';
    }

    private function inferSource(array $event, ?string $project): ?string
    {
        $platform = Arr::get($event, 'platform');
        if ($platform && str_contains($platform, 'javascript')) {
            return 'frontend';
        }
        if ($project && str_contains($project, 'frontend')) {
            return 'frontend';
        }
        if ($project && str_contains($project, 'backend')) {
            return 'backend';
        }
        return null;
    }

    private function generateAiSummary(PlatformError $error, array $event): void
    {
        $ai = app(ErrorAiService::class);
        $payload = [
            'error_type' => Arr::get($event, 'exception.values.0.type'),
            'message' => $error->message,
            'source' => $error->source,
            'route' => $error->route,
            'action' => Arr::get($event, 'transaction'),
            'http_status' => Arr::get($event, 'contexts.response.status_code'),
            'tags' => $error->tags ?? [],
            'release' => $error->release,
            'environment' => $error->environment,
        ];

        $summary = $ai->summarize($payload);
        if (!$summary) {
            if (!$error->ai_category) {
                $error->ai_category = $this->fallbackCategory($error);
                $error->ai_severity = $error->ai_severity ?: 'medium';
                $error->save();
            }
            return;
        }

        $error->ai_category = $summary['category'] ?? $error->ai_category;
        $error->ai_severity = $summary['severity'] ?? $error->ai_severity;
        $error->ai_dev_summary = $summary['dev_summary'] ?? $error->ai_dev_summary;
        $error->ai_user_message_nl = Arr::get($summary, 'user_message.nl') ?? $error->ai_user_message_nl;
        $error->ai_user_message_en = Arr::get($summary, 'user_message.en') ?? $error->ai_user_message_en;
        $error->ai_user_message_de = Arr::get($summary, 'user_message.de') ?? $error->ai_user_message_de;
        $error->ai_user_steps = $summary['user_steps'] ?? $error->ai_user_steps;
        $error->ai_suggested_checks = $summary['suggested_checks'] ?? $error->ai_suggested_checks;
        $error->save();
    }

    private function fallbackCategory(PlatformError $error): string
    {
        $route = (string) $error->route;
        if (str_contains($route, 'bids') || str_contains($route, 'auction')) {
            return 'auction/bidding';
        }
        if (str_contains($route, 'payments') || str_contains($route, 'mollie')) {
            return 'payment';
        }
        if (str_contains($route, 'login') || str_contains($route, 'register')) {
            return 'auth';
        }
        if (str_contains($route, 'yachts')) {
            return 'yachts';
        }
        return 'general';
    }
}
