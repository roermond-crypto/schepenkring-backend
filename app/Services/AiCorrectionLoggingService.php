<?php

namespace App\Services;

use App\Models\BoatFieldChange;
use App\Models\YachtAiExtraction;
use Illuminate\Support\Str;

class AiCorrectionLoggingService
{
    /**
     * Persist one AI extraction run.
     */
    public function createExtraction(array $attributes): YachtAiExtraction
    {
        return YachtAiExtraction::create([
            'session_id' => $attributes['session_id'] ?? (string) Str::uuid(),
            'yacht_id' => $attributes['yacht_id'] ?? null,
            'user_id' => $attributes['user_id'] ?? null,
            'status' => $attributes['status'] ?? 'completed',
            'model_name' => $attributes['model_name'] ?? null,
            'model_version' => $attributes['model_version'] ?? null,
            'hint_text' => $attributes['hint_text'] ?? null,
            'image_count' => (int) ($attributes['image_count'] ?? 0),
            'raw_output_json' => $attributes['raw_output_json'] ?? null,
            'normalized_fields_json' => $attributes['normalized_fields_json'] ?? null,
            'field_confidence_json' => $attributes['field_confidence_json'] ?? null,
            'field_sources_json' => $attributes['field_sources_json'] ?? null,
            'meta_json' => $attributes['meta_json'] ?? null,
            'extracted_at' => $attributes['extracted_at'] ?? now(),
        ]);
    }

    /**
     * Persist one per-field correction/change.
     */
    public function logFieldChange(array $attributes): BoatFieldChange
    {
        return BoatFieldChange::create([
            'yacht_id' => $attributes['yacht_id'],
            'field_name' => (string) $attributes['field_name'],
            'old_value' => $this->encodeValue($attributes['old_value'] ?? null),
            'new_value' => $this->encodeValue($attributes['new_value'] ?? null),
            'changed_by_type' => $attributes['changed_by_type'] ?? 'user',
            'changed_by_id' => $attributes['changed_by_id'] ?? null,
            'source_type' => $attributes['source_type'] ?? null,
            'confidence_before' => $attributes['confidence_before'] ?? null,
            'ai_session_id' => $attributes['ai_session_id'] ?? null,
            'model_name' => $attributes['model_name'] ?? null,
            'reason' => $attributes['reason'] ?? null,
            'correction_label' => $attributes['correction_label'] ?? null,
            'meta' => $attributes['meta'] ?? null,
        ]);
    }

    /**
     * Diff two flat payloads and log all changed fields.
     */
    public function logFieldDiffs(int $yachtId, array $before, array $after, array $context = []): int
    {
        $count = 0;
        $allFields = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));

        foreach ($allFields as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($this->valuesAreEquivalent($old, $new)) {
                continue;
            }

            $this->logFieldChange([
                'yacht_id' => $yachtId,
                'field_name' => $field,
                'old_value' => $old,
                'new_value' => $new,
                'changed_by_type' => $context['changed_by_type'] ?? 'user',
                'changed_by_id' => $context['changed_by_id'] ?? null,
                'source_type' => $context['source_type'] ?? null,
                'confidence_before' => $context['field_confidence'][$field] ?? ($context['confidence_before'] ?? null),
                'ai_session_id' => $context['ai_session_id'] ?? null,
                'model_name' => $context['model_name'] ?? null,
                'reason' => $context['field_reasons'][$field] ?? ($context['reason'] ?? null),
                'correction_label' => $context['field_correction_labels'][$field] ?? ($context['correction_label'] ?? null),
                'meta' => [
                    'scope' => $context['scope'] ?? 'full_form_save',
                ],
            ]);

            $count++;
        }

        return $count;
    }

    public function getFieldHistory(int $yachtId, string $fieldName)
    {
        return BoatFieldChange::where('yacht_id', $yachtId)
            ->where('field_name', $fieldName)
            ->with('user:id,name,email,avatar')
            ->orderByDesc('created_at')
            ->get();
    }

    private function valuesAreEquivalent(mixed $old, mixed $new): bool
    {
        return $this->encodeValue($old) === $this->encodeValue($new);
    }

    private function encodeValue(mixed $value): ?string
    {
        if ($value === '' || $value === 'undefined') {
            return null;
        }

        if ($value === null) {
            return null;
        }

        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return $encoded === false ? (string) $value : $encoded;
    }
}

