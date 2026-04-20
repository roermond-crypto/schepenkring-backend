<?php

namespace App\Services;

use App\Models\KycRule;
use Illuminate\Support\Collection;

class KycRuleEngine
{
    public function evaluateAnswers(iterable $answers, string $audience = 'both'): array
    {
        $answers = $answers instanceof Collection ? $answers : collect($answers);
        $answerMap = [];
        $score = 0;
        $flags = [];
        $reasonCodes = [];
        $outcomeOverride = null;

        foreach ($answers as $answer) {
            $normalized = $this->normalizeValue($answer->normalized_value ?? $answer->answer_value);
            $answerMap[$answer->question_key] = $normalized;

            if ($answer->option) {
                $score += (int) $answer->option->score_delta;
                if ($answer->option->flag_code) {
                    $flags[$answer->option->flag_code] = [
                        'flag_code' => $answer->option->flag_code,
                        'severity' => ((int) $answer->option->score_delta) >= 50 ? 'critical' : 'warning',
                        'message' => $answer->question?->prompt ? ($answer->question->prompt . ': ' . $answer->option->label) : $answer->option->label,
                        'metadata_json' => [
                            'question_key' => $answer->question_key,
                            'option_value' => $answer->option->value,
                        ],
                    ];
                    $reasonCodes[] = $answer->option->flag_code;
                }
            }
        }

        $rules = KycRule::query()
            ->where('is_active', true)
            ->whereIn('audience', ['both', $audience])
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $conditions = $rule->conditions_json ?? [];
            if (!$this->matchesConditions($conditions, $answerMap)) {
                continue;
            }

            $score += (int) $rule->score_delta;
            if ($rule->flag_code) {
                $flags[$rule->flag_code] = [
                    'flag_code' => $rule->flag_code,
                    'severity' => ((int) $rule->score_delta) >= 50 ? 'critical' : 'warning',
                    'message' => $rule->name,
                    'metadata_json' => ['rule_id' => $rule->id],
                ];
                $reasonCodes[] = $rule->flag_code;
            }

            if ($rule->outcome_override) {
                $outcomeOverride = $rule->outcome_override;
            }
        }

        return [
            'score' => $score,
            'flags' => array_values($flags),
            'reason_codes' => array_values(array_unique($reasonCodes)),
            'outcome_override' => $outcomeOverride,
            'answer_map' => $answerMap,
        ];
    }

    private function matchesConditions(array $conditions, array $answers): bool
    {
        if (isset($conditions['match_all']) && is_array($conditions['match_all'])) {
            foreach ($conditions['match_all'] as $condition) {
                if (!$this->matchesSingleCondition($condition, $answers)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($conditions['match_any']) && is_array($conditions['match_any'])) {
            foreach ($conditions['match_any'] as $condition) {
                if ($this->matchesSingleCondition($condition, $answers)) {
                    return true;
                }
            }

            return false;
        }

        return $this->matchesSingleCondition($conditions, $answers);
    }

    private function matchesSingleCondition(array $condition, array $answers): bool
    {
        $questionKey = (string) ($condition['question_key'] ?? '');
        if ($questionKey === '') {
            return false;
        }

        $operator = strtolower((string) ($condition['operator'] ?? 'equals'));
        $expected = $this->normalizeValue($condition['value'] ?? null);
        $actual = $this->normalizeValue($answers[$questionKey] ?? null);

        return match ($operator) {
            'not_equals' => $actual !== $expected,
            'in' => in_array($actual, array_map([$this, 'normalizeValue'], (array) ($condition['value'] ?? [])), true),
            'not_in' => !in_array($actual, array_map([$this, 'normalizeValue'], (array) ($condition['value'] ?? [])), true),
            default => $actual === $expected,
        };
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return strtolower(trim($value));
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if ($value === 1) return 'yes';
        if ($value === 0) return 'no';

        return $value;
    }
}
