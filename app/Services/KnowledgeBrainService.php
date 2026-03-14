<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\FaqKnowledgeDocument;
use App\Models\KnowledgeBrainQuestion;
use App\Models\KnowledgeBrainSuggestion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KnowledgeBrainService
{
    public function __construct(
        private CopilotFuzzyMatcher $matcher,
        private FaqTrainingService $training,
        private LocationAccessService $locations
    ) {
    }

    public function captureCopilotResolution(User $user, string $question, array $response, array $context = []): ?KnowledgeBrainQuestion
    {
        $question = trim($question);
        if ($question === '') {
            return null;
        }

        $strategy = (string) ($response['answer_strategy'] ?? data_get($response, 'knowledge_trace.strategy', ''));
        $confidence = (float) ($response['confidence'] ?? 0.0);
        $shouldCapture = in_array($strategy, ['low_confidence', 'no_match'], true) || $confidence < 0.62;
        if (! $shouldCapture) {
            return null;
        }

        $locationId = $this->resolveLocationId($user, $context);
        if (! $locationId) {
            return null;
        }

        $normalized = $this->matcher->normalize($question);
        if ($normalized === '') {
            return null;
        }

        $matchedFaqId = data_get($response, 'answers.0.sources.0.faq_id');
        $now = now();

        $record = KnowledgeBrainQuestion::query()->firstOrNew([
            'location_id' => $locationId,
            'normalized_question' => $normalized,
        ]);

        $record->question = $question;
        $record->source_type = 'copilot';
        $record->status = $record->exists && $record->status === 'approved' ? 'approved' : 'pending';
        $record->times_asked = $record->exists ? ((int) $record->times_asked + 1) : 1;
        $record->confidence = $confidence;
        $record->matched_faq_id = $matchedFaqId ? (int) $matchedFaqId : null;
        $record->first_seen_at = $record->first_seen_at ?? $now;
        $record->last_seen_at = $now;
        $record->metadata = [
            'strategy' => $strategy,
            'top_source' => data_get($response, 'answers.0.sources.0'),
            'knowledge_trace' => $response['knowledge_trace'] ?? null,
        ];
        $record->save();

        $this->syncMissingQuestionSuggestion($record, $response);

        return $record->fresh(['matchedFaq', 'suggestions']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(User $user, array $filters = []): array
    {
        $locationIds = $this->resolveLocationIds($user, $filters['location_id'] ?? null);

        $faqQuery = $this->scopeFaqs($locationIds);
        $questionQuery = $this->scopeQuestions($locationIds);
        $suggestionQuery = $this->scopeSuggestions($locationIds);
        $documentQuery = $this->scopeDocuments($locationIds);
        $knowledgeItemQuery = $this->scopeKnowledgeItems($locationIds);

        $vectorsStored = (clone $faqQuery)
            ->whereNull('deprecated_at')
            ->whereNotNull('last_indexed_at')
            ->count();
        $pendingEmbeddings = (clone $faqQuery)
            ->whereNull('deprecated_at')
            ->whereNull('last_indexed_at')
            ->count();

        return [
            'overview' => [
                'documents_analyzed' => (clone $documentQuery)->whereIn('status', ['pending_review', 'processing', 'uploaded', 'failed'])->count(),
                'total_qna' => (clone $faqQuery)->whereNull('deprecated_at')->count(),
                'approved_knowledge' => (clone $knowledgeItemQuery)->where('status', 'approved')->count(),
                'pending_review' => (clone $knowledgeItemQuery)->where('status', 'pending')->count(),
                'missing_questions' => (clone $questionQuery)->where('status', 'pending')->count(),
                'suggested_improvements' => (clone $suggestionQuery)->where('type', 'answer_improvement')->where('status', 'pending')->count(),
                'duplicate_clusters' => (clone $suggestionQuery)->where('type', 'duplicate_cluster')->where('status', 'pending')->count(),
            ],
            'missing_questions' => (clone $questionQuery)
                ->with('matchedFaq:id,question')
                ->where('status', 'pending')
                ->orderByDesc('times_asked')
                ->orderByDesc('last_seen_at')
                ->limit(10)
                ->get(),
            'suggested_improvements' => (clone $suggestionQuery)
                ->with(['faq:id,question', 'approvedFaq:id,question'])
                ->whereIn('type', ['answer_improvement', 'duplicate_cluster', 'document_gap', 'missing_question'])
                ->where('status', 'pending')
                ->orderByDesc('last_detected_at')
                ->limit(10)
                ->get(),
            'document_intelligence' => [
                'documents' => (clone $documentQuery)->count(),
                'pending_document_reviews' => (clone $knowledgeItemQuery)->where('status', 'pending')->count(),
                'approved_document_knowledge' => (clone $knowledgeItemQuery)->where('status', 'approved')->count(),
                'detected_document_gaps' => (clone $suggestionQuery)->where('type', 'document_gap')->where('status', 'pending')->count(),
            ],
            'training_status' => [
                'vectors_stored' => $vectorsStored,
                'last_sync' => (clone $faqQuery)->max('last_indexed_at'),
                'pending_embeddings' => $pendingEmbeddings,
                'failed_embeddings' => 0,
                'pinecone_enabled' => (bool) (config('services.pinecone.key') && config('services.pinecone.host')),
            ],
            'evolution' => $this->buildEvolutionMetrics($locationIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listQuestions(User $user, array $filters = []): LengthAwarePaginator
    {
        $locationIds = $this->resolveLocationIds($user, $filters['location_id'] ?? null);
        $query = $this->scopeQuestions($locationIds)
            ->with(['matchedFaq:id,question', 'suggestions'])
            ->orderByDesc('times_asked')
            ->orderByDesc('last_seen_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where('question', 'like', $search);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 25));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listSuggestions(User $user, array $filters = []): LengthAwarePaginator
    {
        $locationIds = $this->resolveLocationIds($user, $filters['location_id'] ?? null);
        $query = $this->scopeSuggestions($locationIds)
            ->with([
                'faq:id,question',
                'questionLog:id,question,times_asked',
                'approvedFaq:id,question',
                'reviewedBy:id,name,email',
            ])
            ->orderByDesc('last_detected_at')
            ->orderByDesc('id');

        foreach (['status', 'type'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('title', 'like', $search)
                    ->orWhere('question', 'like', $search)
                    ->orWhere('summary', 'like', $search);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 25));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function refresh(User $user, array $filters = []): array
    {
        $locationIds = $this->resolveLocationIds($user, $filters['location_id'] ?? null);
        $faqCount = 0;
        $duplicateCount = 0;
        $documentCount = 0;

        foreach ($locationIds as $locationId) {
            $faqs = Faq::query()
                ->where('location_id', $locationId)
                ->whereNull('deprecated_at')
                ->orderBy('id')
                ->get();

            foreach ($faqs as $faq) {
                $review = $this->reviewFaq($faq);
                $faq->forceFill($review)->saveQuietly();
                $faqCount++;

                if ($faq->needs_update) {
                    $this->upsertSuggestion([
                        'location_id' => $locationId,
                        'faq_id' => $faq->id,
                        'fingerprint' => 'answer_improvement:faq:'.$faq->id,
                        'type' => 'answer_improvement',
                        'title' => 'Answer quality review suggests an update',
                        'source_type' => 'faq_review',
                        'question' => $faq->question,
                        'current_answer' => $faq->answer,
                        'suggested_answer' => $faq->ai_suggested_answer,
                        'summary' => $faq->ai_review_summary,
                        'ai_score' => $faq->ai_score,
                        'metadata' => [
                            'faq_id' => $faq->id,
                            'needs_update' => true,
                        ],
                    ]);
                }
            }

            $duplicateCount += $this->refreshDuplicateSuggestions($locationId, $faqs->all());
            $documentCount += $this->refreshDocumentSuggestions($locationId);
        }

        return [
            'faq_reviews' => $faqCount,
            'duplicate_suggestions' => $duplicateCount,
            'document_suggestions' => $documentCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function reviewSuggestion(User $user, KnowledgeBrainSuggestion $suggestion, array $attributes): KnowledgeBrainSuggestion
    {
        return DB::transaction(function () use ($user, $suggestion, $attributes) {
            $status = $attributes['status'] ?? $suggestion->status;
            $suggestion->loadMissing(['faq', 'questionLog', 'approvedFaq']);

            $faq = match ($suggestion->type) {
                'missing_question' => $status === 'approved'
                    ? $this->approveMissingQuestionSuggestion($user, $suggestion, $attributes)
                    : null,
                'answer_improvement' => $status === 'approved'
                    ? $this->approveAnswerImprovementSuggestion($user, $suggestion, $attributes)
                    : null,
                'duplicate_cluster' => $status === 'approved'
                    ? $this->approveDuplicateSuggestion($suggestion, $attributes)
                    : null,
                'document_gap' => $status === 'approved'
                    ? $this->approveDocumentGapSuggestion($user, $suggestion, $attributes)
                    : null,
                default => null,
            };

            if ($status === 'approved') {
                $suggestion->status = 'approved';
                $suggestion->approved_at = now();
                $suggestion->declined_at = null;
            } elseif ($status === 'declined') {
                $suggestion->status = 'declined';
                $suggestion->declined_at = now();
                $suggestion->approved_at = null;
            } else {
                $suggestion->status = 'pending';
            }

            if (array_key_exists('question', $attributes)) {
                $suggestion->question = $attributes['question'];
            }
            if (array_key_exists('summary', $attributes)) {
                $suggestion->summary = $attributes['summary'];
            }
            if (array_key_exists('answer', $attributes)) {
                $suggestion->suggested_answer = $attributes['answer'];
            }

            $suggestion->approved_faq_id = $faq?->id ?? $suggestion->approved_faq_id;
            $suggestion->reviewed_by_user_id = $user->id;
            $suggestion->reviewed_at = now();
            $suggestion->save();

            if ($suggestion->questionLog) {
                $suggestion->questionLog->forceFill([
                    'status' => $status === 'approved' ? 'approved' : ($status === 'declined' ? 'declined' : $suggestion->questionLog->status),
                    'matched_faq_id' => $faq?->id ?? $suggestion->questionLog->matched_faq_id,
                ])->save();
            }

            return $suggestion->fresh(['faq', 'questionLog', 'approvedFaq', 'reviewedBy']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewFaq(Faq $faq): array
    {
        $score = 100;
        $reasons = [];
        $answerLength = mb_strlen(trim((string) $faq->answer));
        $feedbackTotal = (int) $faq->helpful + (int) $faq->not_helpful;
        $helpfulRatio = $feedbackTotal > 0 ? ((int) $faq->helpful / $feedbackTotal) : null;
        $daysOld = $faq->updated_at ? $faq->updated_at->diffInDays(now()) : 0;

        if ($answerLength < 140) {
            $score -= 28;
            $reasons[] = 'Answer is too short for a reliable support response.';
        }

        if ($feedbackTotal >= 5 && $helpfulRatio !== null && $helpfulRatio < 0.6) {
            $score -= 18;
            $reasons[] = 'User feedback suggests the answer is weak or incomplete.';
        }

        if ($daysOld > 180) {
            $score -= 12;
            $reasons[] = 'Answer has not been reviewed recently.';
        }

        if (empty($faq->tags)) {
            $score -= 6;
            $reasons[] = 'Answer has no topical tags to help ranking and reuse.';
        }

        $score = max(0, min(100, $score));
        $needsUpdate = $score < 70;
        $summary = $reasons === []
            ? 'Answer quality looks healthy.'
            : implode(' ', $reasons);

        return [
            'ai_score' => $score,
            'last_reviewed_at' => now(),
            'needs_update' => $needsUpdate,
            'ai_review_summary' => $summary,
            'ai_suggested_answer' => $needsUpdate ? $this->suggestExpandedAnswer($faq) : null,
        ];
    }

    private function suggestExpandedAnswer(Faq $faq): string
    {
        $answer = trim((string) $faq->answer);
        $answer = rtrim($answer, ". \n\t");

        return $answer . '. This answer should explain the normal coverage or process, mention the main conditions or exceptions, and clarify that exact requirements can differ by policy, vessel type, and location-specific rules.';
    }

    /**
     * @param  array<int, Faq>  $faqs
     */
    private function refreshDuplicateSuggestions(int $locationId, array $faqs): int
    {
        $count = 0;
        $seen = [];

        foreach ($faqs as $left) {
            foreach ($faqs as $right) {
                if ($left->id >= $right->id) {
                    continue;
                }

                $pairKey = $left->id.'-'.$right->id;
                if (isset($seen[$pairKey])) {
                    continue;
                }
                $seen[$pairKey] = true;

                $score = $this->duplicateSimilarity($left->question, $right->question);
                if ($score < 0.7) {
                    continue;
                }

                $primary = $this->choosePrimaryFaq($left, $right);
                $duplicate = $primary->id === $left->id ? $right : $left;

                $this->upsertSuggestion([
                    'location_id' => $locationId,
                    'faq_id' => $primary->id,
                    'fingerprint' => 'duplicate_cluster:'.$primary->id.':'.$duplicate->id,
                    'type' => 'duplicate_cluster',
                    'title' => 'Duplicate FAQ cluster detected',
                    'source_type' => 'faq_similarity',
                    'question' => $primary->question,
                    'current_answer' => $primary->answer,
                    'suggested_answer' => null,
                    'summary' => 'These questions appear semantically similar and should be merged into one authoritative answer.',
                    'ai_score' => (int) round($score * 100),
                    'metadata' => [
                        'primary_faq_id' => $primary->id,
                        'duplicate_faq_ids' => [$duplicate->id],
                        'questions' => [
                            $primary->question,
                            $duplicate->question,
                        ],
                        'similarity_score' => round($score, 3),
                    ],
                ]);
                $count++;
            }
        }

        return $count;
    }

    private function refreshDocumentSuggestions(int $locationId): int
    {
        $count = 0;

        $documents = FaqKnowledgeDocument::query()
            ->with(['items' => fn ($query) => $query->where('status', 'pending')->orderBy('id')])
            ->where('location_id', $locationId)
            ->whereIn('status', ['pending_review', 'processing', 'uploaded'])
            ->get();

        foreach ($documents as $document) {
            $pendingItems = $document->items->take(5);
            if ($pendingItems->isEmpty()) {
                continue;
            }

            $this->upsertSuggestion([
                'location_id' => $locationId,
                'faq_id' => null,
                'fingerprint' => 'document_gap:doc:'.$document->id,
                'type' => 'document_gap',
                'title' => 'Document intelligence found uncovered topics',
                'source_type' => 'knowledge_document',
                'question' => null,
                'current_answer' => null,
                'suggested_answer' => null,
                'summary' => 'Document "' . $document->file_name . '" generated pending questions that still need review.',
                'ai_score' => null,
                'metadata' => [
                    'document_id' => $document->id,
                    'document_name' => $document->file_name,
                    'pending_questions' => $pendingItems->pluck('question')->values()->all(),
                    'pending_item_ids' => $pendingItems->pluck('id')->values()->all(),
                ],
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertSuggestion(array $attributes): KnowledgeBrainSuggestion
    {
        $suggestion = KnowledgeBrainSuggestion::query()->firstOrNew([
            'fingerprint' => $attributes['fingerprint'],
        ]);

        $suggestion->fill([
            'location_id' => $attributes['location_id'],
            'faq_id' => $attributes['faq_id'] ?? null,
            'question_id' => $attributes['question_id'] ?? null,
            'type' => $attributes['type'],
            'status' => $suggestion->exists && $suggestion->status === 'approved' ? 'approved' : 'pending',
            'title' => $attributes['title'],
            'source_type' => $attributes['source_type'] ?? 'system',
            'question' => $attributes['question'] ?? null,
            'current_answer' => $attributes['current_answer'] ?? null,
            'suggested_answer' => $attributes['suggested_answer'] ?? null,
            'summary' => $attributes['summary'] ?? null,
            'ai_score' => $attributes['ai_score'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
            'first_detected_at' => $suggestion->first_detected_at ?? now(),
            'last_detected_at' => now(),
        ]);

        $suggestion->save();

        return $suggestion;
    }

    private function syncMissingQuestionSuggestion(KnowledgeBrainQuestion $record, array $response): void
    {
        $matchedFaqId = $record->matched_faq_id;
        $summary = $matchedFaqId
            ? 'Users are asking this in a way that the current FAQ answer does not match confidently enough.'
            : 'AI could not confidently answer this question from the current knowledge base.';

        $this->upsertSuggestion([
            'location_id' => $record->location_id,
            'faq_id' => $matchedFaqId,
            'question_id' => $record->id,
            'fingerprint' => 'missing_question:question:'.$record->id,
            'type' => 'missing_question',
            'title' => 'Suggested new FAQ',
            'source_type' => 'copilot',
            'question' => $record->question,
            'current_answer' => data_get($response, 'answers.0.sources.0.answer'),
            'suggested_answer' => data_get($response, 'answers.0.sources.0.answer'),
            'summary' => $summary,
            'ai_score' => (int) round(((float) $record->confidence) * 100),
            'metadata' => [
                'question_id' => $record->id,
                'times_asked' => $record->times_asked,
                'strategy' => $record->metadata['strategy'] ?? null,
                'knowledge_trace' => $response['knowledge_trace'] ?? null,
            ],
        ]);
    }

    private function approveMissingQuestionSuggestion(User $user, KnowledgeBrainSuggestion $suggestion, array $attributes): Faq
    {
        $question = trim((string) ($attributes['question'] ?? $suggestion->question));
        $answer = trim((string) ($attributes['answer'] ?? $suggestion->suggested_answer));

        if ($question === '' || $answer === '') {
            throw ValidationException::withMessages([
                'answer' => 'Question and answer are required to approve a missing-question suggestion.',
            ]);
        }

        return $this->training->upsertFaq(
            $suggestion->location_id,
            $question,
            $answer,
            $attributes['category'] ?? 'Knowledge Brain',
            null,
            $user,
            [
                'language' => $attributes['language'] ?? null,
                'department' => $attributes['department'] ?? null,
                'visibility' => $attributes['visibility'] ?? 'internal',
                'brand' => $attributes['brand'] ?? null,
                'model' => $attributes['model'] ?? null,
                'tags' => $attributes['tags'] ?? null,
                'source_type' => 'knowledge_brain',
            ],
            null,
            true
        );
    }

    private function approveAnswerImprovementSuggestion(User $user, KnowledgeBrainSuggestion $suggestion, array $attributes): Faq
    {
        $faq = $suggestion->faq;
        if (! $faq) {
            throw ValidationException::withMessages([
                'faq_id' => 'The related FAQ is missing for this improvement suggestion.',
            ]);
        }

        $updatedFaq = $this->training->upsertFaq(
            $faq->location_id,
            trim((string) ($attributes['question'] ?? $faq->question)),
            trim((string) ($attributes['answer'] ?? $suggestion->suggested_answer ?? $faq->answer)),
            $attributes['category'] ?? $faq->category,
            $faq->source_message_id,
            $user,
            [
                'language' => $attributes['language'] ?? $faq->language,
                'department' => $attributes['department'] ?? $faq->department,
                'visibility' => $attributes['visibility'] ?? $faq->visibility,
                'brand' => $attributes['brand'] ?? $faq->brand,
                'model' => $attributes['model'] ?? $faq->model,
                'tags' => $attributes['tags'] ?? $faq->tags,
                'source_type' => $faq->source_type ?: 'knowledge_brain',
            ],
            $faq
        );

        $updatedFaq->forceFill([
            'needs_update' => false,
            'last_reviewed_at' => now(),
            'ai_review_summary' => 'Suggestion approved by admin.',
            'ai_suggested_answer' => null,
            'ai_score' => max(80, (int) ($updatedFaq->ai_score ?? 80)),
        ])->saveQuietly();

        return $updatedFaq->fresh();
    }

    private function approveDuplicateSuggestion(KnowledgeBrainSuggestion $suggestion, array $attributes): ?Faq
    {
        $primaryFaqId = (int) ($attributes['primary_faq_id'] ?? data_get($suggestion->metadata, 'primary_faq_id', 0));
        $duplicateIds = array_values(array_filter(array_map('intval', (array) data_get($suggestion->metadata, 'duplicate_faq_ids', []))));

        $primaryFaq = Faq::query()->whereKey($primaryFaqId)->first();
        if (! $primaryFaq || $duplicateIds === []) {
            throw ValidationException::withMessages([
                'primary_faq_id' => 'A primary FAQ and duplicate FAQ list are required to approve this duplicate suggestion.',
            ]);
        }

        $duplicates = Faq::query()->whereIn('id', $duplicateIds)->get();
        foreach ($duplicates as $duplicate) {
            $this->training->deprecateFaq($duplicate, $primaryFaq);
        }

        return $primaryFaq;
    }

    private function approveDocumentGapSuggestion(User $user, KnowledgeBrainSuggestion $suggestion, array $attributes): ?Faq
    {
        $question = trim((string) ($attributes['question'] ?? ''));
        $answer = trim((string) ($attributes['answer'] ?? ''));

        if ($question === '' || $answer === '') {
            return null;
        }

        return $this->training->upsertFaq(
            $suggestion->location_id,
            $question,
            $answer,
            $attributes['category'] ?? 'Document Intelligence',
            null,
            $user,
            [
                'language' => $attributes['language'] ?? null,
                'department' => $attributes['department'] ?? null,
                'visibility' => $attributes['visibility'] ?? 'internal',
                'brand' => $attributes['brand'] ?? null,
                'model' => $attributes['model'] ?? null,
                'tags' => $attributes['tags'] ?? null,
                'source_type' => 'knowledge_document',
            ],
            null,
            true
        );
    }

    private function choosePrimaryFaq(Faq $left, Faq $right): Faq
    {
        $leftScore = ((int) $left->helpful * 2) - (int) $left->not_helpful;
        $rightScore = ((int) $right->helpful * 2) - (int) $right->not_helpful;

        if ($leftScore === $rightScore) {
            return $left->id <= $right->id ? $left : $right;
        }

        return $leftScore > $rightScore ? $left : $right;
    }

    private function duplicateSimilarity(string $left, string $right): float
    {
        $fuzzyScore = $this->matcher->score($left, $right);
        $leftTokens = $this->normalizeTokens($left);
        $rightTokens = $this->normalizeTokens($right);

        if ($leftTokens === [] || $rightTokens === []) {
            return $fuzzyScore;
        }

        $intersection = array_intersect($leftTokens, $rightTokens);
        $union = array_unique(array_merge($leftTokens, $rightTokens));
        $tokenScore = count($union) > 0 ? (count($intersection) / count($union)) : 0.0;

        return max($fuzzyScore, $tokenScore);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTokens(string $value): array
    {
        $tokens = explode(' ', $this->matcher->normalize($value));
        $stopWords = [
            'how', 'what', 'can', 'i', 'my', 'do', 'does', 'the', 'a', 'an', 'to', 'get', 'while',
            'is', 'in', 'it', 'of', 'for', 'and', 'on',
        ];

        $normalized = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || in_array($token, $stopWords, true)) {
                continue;
            }

            if (str_ends_with($token, 'ing')) {
                $token = substr($token, 0, -3);
            } elseif (str_ends_with($token, 'ed')) {
                $token = substr($token, 0, -2);
            } elseif (str_ends_with($token, 'ance') || str_ends_with($token, 'ence')) {
                $token = substr($token, 0, -4);
            } elseif (str_ends_with($token, 'tion')) {
                $token = substr($token, 0, -4);
            } elseif (str_ends_with($token, 's') && strlen($token) > 4) {
                $token = substr($token, 0, -1);
            }

            if (str_ends_with($token, 'e') && strlen($token) > 4) {
                $token = substr($token, 0, -1);
            }

            $normalized[] = $token;
        }

        return array_values(array_unique(array_filter($normalized)));
    }

    /**
     * @return array<int>
     */
    private function resolveLocationIds(User $user, mixed $locationId): array
    {
        if ($locationId) {
            return [(int) $locationId];
        }

        if ($user->isAdmin()) {
            return DB::table('locations')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $this->locations->accessibleLocationIds($user);
    }

    private function resolveLocationId(User $user, array $context): ?int
    {
        $contextLocationId = (int) ($context['location_id'] ?? $context['harbor_id'] ?? 0);
        if ($contextLocationId > 0) {
            return $contextLocationId;
        }

        return $user->location_id ?: $user->client_location_id;
    }

    private function scopeFaqs(array $locationIds): Builder
    {
        return Faq::query()->whereIn('location_id', $locationIds);
    }

    private function scopeQuestions(array $locationIds): Builder
    {
        return KnowledgeBrainQuestion::query()->whereIn('location_id', $locationIds);
    }

    private function scopeSuggestions(array $locationIds): Builder
    {
        return KnowledgeBrainSuggestion::query()->whereIn('location_id', $locationIds);
    }

    private function scopeDocuments(array $locationIds): Builder
    {
        return FaqKnowledgeDocument::query()->whereIn('location_id', $locationIds);
    }

    private function scopeKnowledgeItems(array $locationIds): Builder
    {
        return \App\Models\FaqKnowledgeItem::query()->whereIn('location_id', $locationIds);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildEvolutionMetrics(array $locationIds): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->map(function (int $offset) use ($start) {
            $month = $start->copy()->addMonths($offset);

            return [
                'month' => $month->format('Y-m'),
                'label' => $month->format('F Y'),
                'faqs_created' => 0,
                'questions_captured' => 0,
                'suggestions_approved' => 0,
            ];
        })->keyBy('month');

        $faqCounts = Faq::query()
            ->whereIn('location_id', $locationIds)
            ->get(['created_at'])
            ->groupBy(fn (Faq $faq) => optional($faq->created_at)->format('Y-m'))
            ->map->count();

        $questionCounts = KnowledgeBrainQuestion::query()
            ->whereIn('location_id', $locationIds)
            ->get(['created_at'])
            ->groupBy(fn (KnowledgeBrainQuestion $question) => optional($question->created_at)->format('Y-m'))
            ->map->count();

        $approvedSuggestionCounts = KnowledgeBrainSuggestion::query()
            ->whereIn('location_id', $locationIds)
            ->whereNotNull('approved_at')
            ->get(['approved_at'])
            ->groupBy(fn (KnowledgeBrainSuggestion $suggestion) => optional($suggestion->approved_at)->format('Y-m'))
            ->map->count();

        return $months->map(function (array $row) use ($faqCounts, $questionCounts, $approvedSuggestionCounts) {
            $month = $row['month'];
            $row['faqs_created'] = (int) ($faqCounts[$month] ?? 0);
            $row['questions_captured'] = (int) ($questionCounts[$month] ?? 0);
            $row['suggestions_approved'] = (int) ($approvedSuggestionCounts[$month] ?? 0);

            return $row;
        })->values()->all();
    }
}
