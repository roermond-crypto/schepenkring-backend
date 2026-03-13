<?php

namespace App\Services;

use App\Models\BoatFieldChange;

class BoatFieldFeedbackService
{
    public function buildOptionalEquipmentPromptHint(array $fields, int $sampleLimit = 400): string
    {
        $changes = BoatFieldChange::query()
            ->whereIn('field_name', $fields)
            ->whereNotNull('ai_session_id')
            ->latest('id')
            ->limit($sampleLimit)
            ->get(['field_name', 'new_value', 'meta']);

        $stats = [];

        foreach ($changes as $change) {
            $meta = is_array($change->meta) ? $change->meta : [];
            $aiProposed = $this->normalizeEquipmentValue($meta['ai_proposed_value'] ?? null);
            $finalValue = $this->normalizeEquipmentValue($this->decodeLoggedValue($change->new_value));

            if ($aiProposed === null || $finalValue === null) {
                continue;
            }

            $field = (string) $change->field_name;
            $stats[$field] ??= [
                'samples' => 0,
                'ai_yes' => 0,
                'ai_yes_corrected' => 0,
                'ai_no' => 0,
                'ai_no_corrected' => 0,
            ];

            $stats[$field]['samples']++;

            if ($aiProposed === 'yes') {
                $stats[$field]['ai_yes']++;
                if ($finalValue !== 'yes') {
                    $stats[$field]['ai_yes_corrected']++;
                }
            }

            if ($aiProposed === 'no') {
                $stats[$field]['ai_no']++;
                if ($finalValue !== 'no') {
                    $stats[$field]['ai_no_corrected']++;
                }
            }
        }

        $lines = [];
        foreach ($stats as $field => $fieldStats) {
            if ($fieldStats['ai_yes'] >= 2) {
                $yesCorrectionRate = $fieldStats['ai_yes_corrected'] / max(1, $fieldStats['ai_yes']);
                if ($yesCorrectionRate >= 0.5) {
                    $lines[] = "- {$field}: AI 'yes' guesses were corrected in {$fieldStats['ai_yes_corrected']} of {$fieldStats['ai_yes']} recent reviewed cases. Only return yes with explicit evidence.";
                }
            }

            if ($fieldStats['ai_no'] >= 2) {
                $noCorrectionRate = $fieldStats['ai_no_corrected'] / max(1, $fieldStats['ai_no']);
                if ($noCorrectionRate >= 0.5) {
                    $lines[] = "- {$field}: AI 'no' guesses were corrected in {$fieldStats['ai_no_corrected']} of {$fieldStats['ai_no']} recent reviewed cases. Prefer null when evidence is weak.";
                }
            }
        }

        if ($lines === []) {
            return '';
        }

        return "HISTORICAL CORRECTION FEEDBACK:\n"
            . "Use recent reviewed corrections as a caution layer for optional equipment fields.\n"
            . implode("\n", array_slice($lines, 0, 8));
    }

    private function decodeLoggedValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function normalizeEquipmentValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'yes', 'y', 'true', 'present', 'included', 'installed', 'available', '1' => 'yes',
            'no', 'n', 'false', 'absent', 'not installed', 'not available', 'none', '0' => 'no',
            'unknown', 'unsure', 'uncertain', 'not sure', 'maybe' => 'unknown',
            default => null,
        };
    }
}
