<?php

namespace App\Jobs;

use App\Models\FaqEntry;
use App\Models\FaqTranslation;
use App\Models\GlossaryTerm;
use App\Services\FaqAiService;
use App\Services\TranslationHashService;
use App\Support\TranslationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranslateFaq implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $faqId,
        public string $targetLanguage,
        public ?string $sourceLanguage = null,
        public bool $force = false
    ) {
    }

    public function handle(FaqAiService $ai, TranslationHashService $hasher): void
    {
        $faq = FaqEntry::find($this->faqId);
        if (!$faq) {
            return;
        }

        $target = strtolower($this->targetLanguage);
        $existing = FaqTranslation::where('faq_id', $faq->id)
            ->where('language', $target)
            ->first();

        if ($existing && !$this->force) {
            return;
        }

        $source = $this->resolveSourceTranslation($faq, $target);
        if (!$source) {
            return;
        }

        $glossary = GlossaryTerm::all()->map(function (GlossaryTerm $term) {
            return $term->toArray();
        })->toArray();

        $result = $ai->translate($source, $target, $glossary);
        if (!$result) {
            return;
        }

        $translation = $existing ?? new FaqTranslation();
        $translation->faq_id = $faq->id;
        $translation->language = $target;
        $translation->question = $result['question'];
        $translation->answer = $result['answer'];
        $translation->long_description = null;
        $translation->long_description_status = 'pending';
        $translation->needs_review = true;
        $translation->translation_status = TranslationStatus::AI_DRAFT;
        $translation->source_language = $source->language;
        $translation->translated_from_translation_id = $source->id;
        $translation->source_hash = $hasher->hash([
            'question' => $translation->question,
            'answer' => $translation->answer,
        ]);
        $translation->translated_from_hash = $hasher->hash([
            'question' => $source->question,
            'answer' => $source->answer,
        ]);
        $translation->indexed_at = null;
        $translation->save();

        Log::info('FAQ translation generated', [
            'faq_id' => $faq->id,
            'language' => $target,
        ]);
    }

    private function resolveSourceTranslation(FaqEntry $faq, string $target): ?FaqTranslation
    {
        $preferred = [];
        if ($this->sourceLanguage) {
            $preferred[] = strtolower($this->sourceLanguage);
        }

        if ($target === 'en') {
            $preferred[] = 'nl';
        } elseif ($target === 'de') {
            $preferred[] = 'en';
            $preferred[] = 'nl';
        } else {
            $preferred[] = 'en';
            $preferred[] = 'nl';
        }

        foreach ($preferred as $language) {
            $translation = FaqTranslation::where('faq_id', $faq->id)
                ->where('language', $language)
                ->first();
            if ($translation) {
                return $translation;
            }
        }

        return FaqTranslation::where('faq_id', $faq->id)->first();
    }
}
