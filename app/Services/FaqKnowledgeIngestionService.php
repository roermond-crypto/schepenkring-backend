<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\FaqKnowledgeDocument;
use App\Models\FaqKnowledgeItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FaqKnowledgeIngestionService
{
    public function __construct(
        private FaqKnowledgeTextExtractor $extractor,
        private FaqKnowledgeQaGeneratorService $generator,
        private FaqTrainingService $training
    ) {
    }

    public function ingest(User $user, UploadedFile $file, array $attributes): FaqKnowledgeDocument
    {
        $document = FaqKnowledgeDocument::create([
            'location_id' => (int) $attributes['location_id'],
            'uploaded_by_user_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->storeAs(
                'faq-knowledge',
                now()->format('Ymd_His').'_'.$this->safeFileName($file->getClientOriginalName()),
                'local'
            ),
            'mime_type' => $file->getMimeType(),
            'extension' => strtolower($file->getClientOriginalExtension()),
            'source_type' => $this->sourceType($attributes, $file),
            'status' => 'processing',
            'language' => $attributes['language'] ?? null,
            'category' => $attributes['category'] ?? null,
            'department' => $attributes['department'] ?? null,
            'visibility' => $attributes['visibility'] ?? 'internal',
            'brand' => $attributes['brand'] ?? null,
            'model' => $attributes['model'] ?? null,
            'tags' => $attributes['tags'] ?? null,
            'metadata' => [
                'original_size' => $file->getSize(),
            ],
        ]);

        try {
            $text = $this->extractor->extract($file);
            if ($text === '') {
                throw ValidationException::withMessages([
                    'file' => 'No readable text could be extracted from this document.',
                ]);
            }

            $chunks = $this->chunkText($text);
            $count = 0;

            DB::transaction(function () use ($document, $attributes, $chunks, $text, &$count) {
                foreach ($chunks as $index => $chunk) {
                    $qas = $this->generator->generate($chunk, $attributes['language'] ?? null);

                    foreach ($qas as $qa) {
                        FaqKnowledgeItem::create([
                            'document_id' => $document->id,
                            'location_id' => $document->location_id,
                            'chunk_index' => $index,
                            'status' => 'pending',
                            'source_type' => $document->source_type,
                            'language' => $attributes['language'] ?? null,
                            'category' => $attributes['category'] ?? null,
                            'department' => $attributes['department'] ?? null,
                            'visibility' => $attributes['visibility'] ?? 'internal',
                            'brand' => $attributes['brand'] ?? null,
                            'model' => $attributes['model'] ?? null,
                            'tags' => $attributes['tags'] ?? null,
                            'question' => $qa['question'],
                            'answer' => $qa['answer'],
                            'source_excerpt' => $chunk,
                            'metadata' => [
                                'chunk_index' => $index,
                            ],
                        ]);
                        $count++;
                    }
                }

                $document->forceFill([
                    'status' => 'pending_review',
                    'extracted_text' => $text,
                    'chunk_count' => count($chunks),
                    'generated_qna_count' => $count,
                    'processed_at' => now(),
                    'processing_error' => null,
                ])->save();
            });
        } catch (\Throwable $e) {
            $document->forceFill([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        return $document->fresh(['items']);
    }

    public function review(User $user, FaqKnowledgeItem $item, array $attributes): FaqKnowledgeItem
    {
        $item->loadMissing('approvedFaq', 'document');

        $status = $attributes['status'] ?? $item->status;
        $item->question = $attributes['question'] ?? $item->question;
        $item->answer = $attributes['answer'] ?? $item->answer;
        $item->category = $attributes['category'] ?? $item->category;
        $item->language = $attributes['language'] ?? $item->language;
        $item->department = $attributes['department'] ?? $item->department;
        $item->visibility = $attributes['visibility'] ?? $item->visibility;
        $item->brand = $attributes['brand'] ?? $item->brand;
        $item->model = $attributes['model'] ?? $item->model;
        $item->tags = $attributes['tags'] ?? $item->tags;
        $item->review_notes = $attributes['review_notes'] ?? $item->review_notes;
        $item->reviewed_by_user_id = $user->id;

        if ($status === 'approved') {
            $faq = $this->training->upsertFaq(
                $item->location_id,
                $item->question,
                $item->answer,
                $item->category,
                null,
                $user,
                [
                    'language' => $item->language,
                    'department' => $item->department,
                    'visibility' => $item->visibility,
                    'brand' => $item->brand,
                    'model' => $item->model,
                    'tags' => $item->tags,
                    'source_type' => $item->source_type,
                ],
                $item->approvedFaq,
                $item->approved_faq_id === null
            );

            $item->status = 'approved';
            $item->approved_faq_id = $faq->id;
            $item->approved_at = now();
            $item->declined_at = null;
        } else {
            $this->removeApprovedFaq($item);

            $item->status = $status;
            $item->approved_at = null;
            $item->declined_at = $status === 'declined' ? now() : null;
        }

        $item->save();

        return $item->fresh(['approvedFaq', 'document']);
    }

    public function deleteItem(FaqKnowledgeItem $item): void
    {
        $item->loadMissing('approvedFaq');
        $this->removeApprovedFaq($item);
        $item->delete();
    }

    /**
     * @return array<string, int>
     */
    public function analyticsFor(User $user, LocationAccessService $locations): array
    {
        $documentQuery = $locations->scopeQuery(FaqKnowledgeDocument::query(), $user);
        $itemQuery = $locations->scopeQuery(FaqKnowledgeItem::query(), $user);

        return [
            'documents_uploaded' => (clone $documentQuery)->count(),
            'generated_qna_total' => (clone $itemQuery)->count(),
            'pending_reviews' => (clone $itemQuery)->where('status', 'pending')->count(),
            'approved_knowledge' => (clone $itemQuery)->where('status', 'approved')->count(),
            'declined_knowledge' => (clone $itemQuery)->where('status', 'declined')->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function chunkText(string $text): array
    {
        $paragraphs = preg_split("/\n{2,}/", trim($text)) ?: [];
        $chunks = [];
        $buffer = '';
        $targetLength = 3500;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;
            if (mb_strlen($candidate) <= $targetLength) {
                $buffer = $candidate;
                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $buffer;
            }

            if (mb_strlen($paragraph) <= $targetLength) {
                $buffer = $paragraph;
                continue;
            }

            foreach (mb_str_split($paragraph, $targetLength) as $segment) {
                $chunks[] = trim($segment);
            }
            $buffer = '';
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks === [] ? [trim($text)] : $chunks;
    }

    private function removeApprovedFaq(FaqKnowledgeItem $item): void
    {
        if ($item->approvedFaq instanceof Faq) {
            $this->training->deleteFaq($item->approvedFaq);
        }

        $item->approved_faq_id = null;
    }

    private function sourceType(array $attributes, UploadedFile $file): string
    {
        if (! empty($attributes['source_type'])) {
            return (string) $attributes['source_type'];
        }

        return match (strtolower($file->getClientOriginalExtension())) {
            'pdf' => 'pdf_document',
            'xlsx' => 'excel_import',
            'docx' => 'word_document',
            'csv' => 'csv_import',
            'md' => 'markdown_document',
            default => 'text_document',
        };
    }

    private function safeFileName(string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $base) ?: 'document';

        return $safeBase.($extension ? '.'.$extension : '');
    }
}
