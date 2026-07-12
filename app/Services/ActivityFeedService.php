<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\User;

/**
 * The single call-site for the UI-facing per-entity activity feed (user,
 * seller, buyer, yacht, location, deal, campaign timelines, Sales Command
 * Center). Deliberately separate from AuditLog, which keeps its existing
 * compliance/security purpose (risk_level, snapshots, actor) — record()
 * here is for "what happened, in plain terms, that a human browsing a
 * timeline would want to see," not a forensic trail.
 */
class ActivityFeedService
{
    public function record(
        string $subjectType,
        int|string $subjectId,
        string $type,
        string $summary,
        array $payload = [],
        ?User $actor = null,
    ): ActivityEvent {
        return ActivityEvent::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'type' => $type,
            'actor_user_id' => $actor?->id,
            'summary' => $summary,
            'payload' => $payload,
        ]);
    }

    public function forSubject(string $subjectType, int|string $subjectId, int $limit = 50)
    {
        return ActivityEvent::forSubject($subjectType, $subjectId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
