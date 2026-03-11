<?php

namespace App\Services;

use App\Models\CopilotActionPhrase;
use App\Models\User;

class CopilotActionMatcherService
{
    public function __construct(
        private CopilotFuzzyMatcher $matcher,
        private CopilotPermissionService $permissionService
    ) {
    }

    public function match(string $input, User $user, ?string $language = null, ?string $module = null, int $limit = 5): array
    {
        $normalizedInput = $this->matcher->normalize($input);
        if ($normalizedInput === '') {
            return [];
        }

        $query = CopilotActionPhrase::query()
            ->where('enabled', true)
            ->with('action');

        if ($language) {
            $query->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            });
        }

        $phrases = $query->get();
        $candidates = [];

        foreach ($phrases as $phrase) {
            if (!$phrase->action || !$phrase->action->enabled) {
                continue;
            }

            if (! $this->permissionService->canUseAction(
                $user,
                $phrase->action->permission_key,
                $phrase->action->required_role
            )) {
                continue;
            }

            if ($module && $phrase->action->module !== $module) {
                continue;
            }

            $normalizedPhrase = $this->matcher->normalize($phrase->phrase);
            if ($normalizedPhrase === '') {
                continue;
            }

            $score = 0.0;
            if (str_contains($normalizedInput, $normalizedPhrase)) {
                $score = 0.85;
            } else {
                $score = $this->matcher->score($normalizedInput, $normalizedPhrase) * 0.8;
            }

            if ($phrase->priority > 0) {
                $score += min(0.1, $phrase->priority / 100);
            }

            if ($module && $phrase->action->module === $module) {
                $score += 0.05;
            }

            if ($score < 0.35) {
                continue;
            }

            $candidates[] = [
                'action_id' => $phrase->action->action_id,
                'title' => $phrase->action->title,
                'required_params' => $phrase->action->required_params ?? [],
                'input_schema' => $phrase->action->input_schema,
                'score' => round(min(1.0, $score), 3),
                'reason' => 'Matched phrase: ' . $phrase->phrase,
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, $limit);
    }
}
