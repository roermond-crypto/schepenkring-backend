<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\Message;
use App\Models\User;
use App\Support\CopilotLanguage;
use Illuminate\Validation\ValidationException;

class FaqTrainingService
{
    public function __construct(
        private CopilotMemoryService $memory,
        private CopilotLanguage $language,
        private KnowledgeGraphService $graph
    ) {
    }

    public function upsertFaq(
        int $locationId,
        string $question,
        string $answer,
        ?string $category = null,
        ?string $sourceMessageId = null,
        ?User $trainer = null,
        array $attributes = [],
        ?Faq $existingFaq = null,
        bool $forceCreate = false
    ): Faq {
        $question = trim($question);
        $answer = trim($answer);

        if ($question === '' || $answer === '') {
            throw ValidationException::withMessages([
                'faq' => 'Question and answer are required.',
            ]);
        }

        if ($existingFaq) {
            $faq = $existingFaq;
        } elseif ($forceCreate) {
            $faq = new Faq();
        } else {
            $faq = Faq::query()->firstOrNew([
                'location_id' => $locationId,
                'question' => $question,
            ]);
        }

        $normalizedTags = $this->normalizeTags($attributes['tags'] ?? $faq->tags ?? []);
        $language = $this->language->normalize($attributes['language'] ?? null)
            ?? $this->language->detectFromText($question . ' ' . $answer)
            ?? $this->language->normalize($trainer?->locale)
            ?? $faq->language
            ?? 'en';

        $faq->location_id = $locationId;
        $faq->question = $question;
        $faq->answer = $answer;
        $faq->category = $category ?: ($faq->category ?: 'Chat');
        $faq->language = $language;
        $faq->department = $this->normalizeNullableString($attributes['department'] ?? $faq->department);
        $faq->visibility = $this->normalizeVisibility($attributes['visibility'] ?? $faq->visibility);
        $faq->brand = $this->normalizeNullableString($attributes['brand'] ?? $faq->brand);
        $faq->model = $this->normalizeNullableString($attributes['model'] ?? $faq->model);
        $faq->tags = $normalizedTags === [] ? null : $normalizedTags;
        $faq->source_type = $this->normalizeNullableString($attributes['source_type'] ?? $faq->source_type) ?: 'faq';
        $faq->deprecated_at = null;
        $faq->superseded_by_faq_id = null;
        $faq->source_message_id = $sourceMessageId;
        $faq->trained_by_user_id = $trainer?->id;
        $faq->save();

        return $this->syncFaq($faq);
    }

    public function syncFaq(Faq $faq): Faq
    {
        if ($this->memory->rememberFaq($faq)) {
            $faq->forceFill([
                'last_indexed_at' => now(),
            ])->saveQuietly();
        }

        $faq = $faq->fresh();
        $this->graph->syncFaq($faq);

        return $faq;
    }

    /**
     * @return array{faq:Faq,question_message:Message}
     */
    public function trainFromMessage(Message $message, User $trainer): array
    {
        $message->loadMissing('conversation');

        if (! $message->conversation || ! $message->conversation->location_id) {
            throw ValidationException::withMessages([
                'message' => 'Conversation location is required to train FAQ.',
            ]);
        }

        if ($message->sender_type === 'visitor') {
            throw ValidationException::withMessages([
                'message' => 'Only staff or AI answers can be trained into FAQ.',
            ]);
        }

        $answer = trim((string) ($message->text ?: $message->body));
        if ($answer === '') {
            throw ValidationException::withMessages([
                'message' => 'Answer text is required to train FAQ.',
            ]);
        }

        $questionMessage = Message::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('sender_type', 'visitor')
            ->where('created_at', '<=', $message->created_at)
            ->orderByDesc('created_at')
            ->first();

        $question = trim((string) ($questionMessage?->text ?: $questionMessage?->body));
        if (! $questionMessage || $question === '') {
            throw ValidationException::withMessages([
                'message' => 'No visitor question was found before this answer.',
            ]);
        }

        $faq = $this->upsertFaq(
            (int) $message->conversation->location_id,
            $question,
            $answer,
            'Chat',
            $message->id,
            $trainer
        );

        $metadata = $message->metadata ?? [];
        $thumbsUpUserIds = array_values(array_unique(array_map('intval', $metadata['faq_thumbsup_user_ids'] ?? [])));
        $alreadyApproved = in_array($trainer->id, $thumbsUpUserIds, true);

        if (! $alreadyApproved) {
            $thumbsUpUserIds[] = $trainer->id;
            $faq->increment('helpful');
        }

        $message->metadata = array_merge($metadata, [
            'faq_id' => $faq->id,
            'faq_question_message_id' => $questionMessage->id,
            'faq_trained_at' => now()->toIso8601String(),
            'faq_trained_by_user_id' => $trainer->id,
            'faq_thumbsup_user_ids' => $thumbsUpUserIds,
        ]);
        $message->save();

        return [
            'faq' => $faq->fresh(),
            'question_message' => $questionMessage,
        ];
    }

    public function deprecateFaq(Faq $faq, ?Faq $replacement = null): void
    {
        $faq->forceFill([
            'deprecated_at' => now(),
            'superseded_by_faq_id' => $replacement?->id,
        ])->saveQuietly();

        $this->memory->forgetFaq($faq);
        $this->graph->markFaqDeprecated($faq->fresh(), $replacement?->fresh());
    }

    public function deleteFaq(Faq $faq): void
    {
        $this->memory->forgetFaq($faq);
        $this->graph->removeFaq($faq);
        $faq->delete();
    }

    /**
     * @param  array<int, mixed>|mixed  $tags
     * @return array<int, string>
     */
    private function normalizeTags($tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $normalized = array_values(array_unique(array_filter(array_map(function ($tag) {
            if (! is_string($tag)) {
                return null;
            }

            $tag = trim($tag);

            return $tag === '' ? null : $tag;
        }, $tags))));

        return array_slice($normalized, 0, 20);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeVisibility(mixed $value): string
    {
        $value = $this->normalizeNullableString($value) ?? 'internal';

        return in_array($value, ['internal', 'staff', 'public'], true) ? $value : 'internal';
    }
}
