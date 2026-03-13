<?php

namespace App\Services;

use App\Models\CopilotAuditEvent;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CopilotFeedbackService
{
    public function __construct(
        private FaqTrainingService $training,
        private LocationAccessService $locations
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function capture(User $user, array $payload): array
    {
        $auditEvent = ! empty($payload['audit_event_id'])
            ? CopilotAuditEvent::query()->find($payload['audit_event_id'])
            : null;

        $faq = ! empty($payload['faq_id'])
            ? Faq::query()->findOrFail((int) $payload['faq_id'])
            : null;

        $supersededFaq = ! empty($payload['supersede_faq_id'])
            ? Faq::query()->findOrFail((int) $payload['supersede_faq_id'])
            : $this->resolveSupersededFaqFromAudit($auditEvent);

        $locationId = $faq?->location_id
            ?? $supersededFaq?->location_id
            ?? (isset($payload['location_id']) ? (int) $payload['location_id'] : null);

        if (! $locationId) {
            throw ValidationException::withMessages([
                'location_id' => 'A location_id or existing faq_id is required.',
            ]);
        }

        if (! $this->locations->sharesLocation($user, $locationId)) {
            abort(403, 'Forbidden');
        }

        $question = trim((string) ($payload['question']
            ?? $faq?->question
            ?? $auditEvent?->input_text
            ?? ''));
        $wrongAnswer = trim((string) ($payload['wrong_answer']
            ?? data_get($auditEvent?->matching_detail, 'answers.0.answer')
            ?? ''));

        if ($question === '') {
            throw ValidationException::withMessages([
                'question' => 'A question is required to store feedback.',
            ]);
        }

        $correctionAttributes = array_merge($payload, [
            'source_type' => $payload['source_type'] ?? 'faq',
        ]);

        $correctedFaq = $this->training->upsertFaq(
            $locationId,
            $question,
            (string) $payload['corrected_answer'],
            $payload['category'] ?? $faq?->category ?? $supersededFaq?->category,
            $faq?->source_message_id ?? $supersededFaq?->source_message_id,
            $user,
            $correctionAttributes,
            $faq,
            $faq === null && $supersededFaq !== null
        );

        if ($supersededFaq && $supersededFaq->id !== $correctedFaq->id) {
            $this->training->deprecateFaq($supersededFaq, $correctedFaq);
        }

        $feedbackEvent = CopilotAuditEvent::create([
            'user_id' => $user->id,
            'source' => 'learning',
            'stage' => 'feedback',
            'input_text' => $question,
            'selected_action_id' => null,
            'confidence' => 1.0,
            'status' => 'corrected',
            'failure_reason' => null,
            'matching_detail' => [
                'audit_event_id' => $auditEvent?->id,
                'original_question' => $question,
                'wrong_answer' => $wrongAnswer !== '' ? $wrongAnswer : null,
                'corrected_answer' => $payload['corrected_answer'],
                'faq_id' => $correctedFaq->id,
                'superseded_faq_id' => $supersededFaq?->id,
            ],
            'request_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now(),
        ]);

        return [
            'message' => 'Feedback stored',
            'faq' => $correctedFaq,
            'feedback_event_id' => $feedbackEvent->id,
            'superseded_faq_id' => $supersededFaq?->id,
        ];
    }

    private function resolveSupersededFaqFromAudit(?CopilotAuditEvent $auditEvent): ?Faq
    {
        $faqId = data_get($auditEvent?->matching_detail, 'knowledge_trace.used_source_ids.0')
            ?? data_get($auditEvent?->matching_detail, 'answers.0.sources.0.faq_id');

        if (! is_numeric($faqId)) {
            return null;
        }

        return Faq::query()->find((int) $faqId);
    }
}
