<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\Message;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FaqTrainingService
{
    public function __construct(private CopilotMemoryService $memory)
    {
    }

    public function upsertFaq(
        int $locationId,
        string $question,
        string $answer,
        ?string $category = null,
        ?string $sourceMessageId = null,
        ?User $trainer = null
    ): Faq {
        $question = trim($question);
        $answer = trim($answer);

        if ($question === '' || $answer === '') {
            throw ValidationException::withMessages([
                'faq' => 'Question and answer are required.',
            ]);
        }

        $faq = Faq::query()->firstOrNew([
            'location_id' => $locationId,
            'question' => $question,
        ]);

        $faq->answer = $answer;
        $faq->category = $category ?: ($faq->category ?: 'Chat');
        $faq->source_message_id = $sourceMessageId;
        $faq->trained_by_user_id = $trainer?->id;
        $faq->save();

        $this->memory->rememberFaq($faq);

        return $faq->fresh();
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

    public function deleteFaq(Faq $faq): void
    {
        $this->memory->forgetFaq($faq);
        $faq->delete();
    }
}
