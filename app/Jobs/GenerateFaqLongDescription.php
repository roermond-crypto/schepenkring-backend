<?php

namespace App\Jobs;

use App\Models\FaqTranslation;
use App\Models\GlossaryTerm;
use App\Services\FaqAiService;
use App\Services\FaqPineconeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateFaqLongDescription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $translationId, public bool $force = false)
    {
    }

    public function handle(FaqAiService $ai, ?FaqPineconeService $pinecone = null): void
    {
        $translation = FaqTranslation::with('faq')->find($this->translationId);
        if (!$translation) {
            return;
        }

        if (!$this->force && $translation->long_description_status === 'ready' && $translation->long_description) {
            return;
        }

        $translation->long_description_status = 'generating';
        $translation->save();

        $glossary = GlossaryTerm::all()->map(function (GlossaryTerm $term) {
            return $term->toArray();
        })->toArray();

        $minWords = (int) env('FAQ_LONG_DESCRIPTION_MIN_WORDS', 120);
        $maxWords = (int) env('FAQ_LONG_DESCRIPTION_MAX_WORDS', 220);

        $text = $ai->generateLongDescription($translation, $glossary, $minWords, $maxWords);
        if (!$text) {
            $translation->long_description_status = 'failed';
            $translation->save();
            return;
        }

        $translation->long_description = $text;
        $translation->long_description_status = 'ready';

        if (filter_var(env('FAQ_REINDEX_ON_LONG_DESCRIPTION', false), FILTER_VALIDATE_BOOL)) {
            $translation->indexed_at = null;
        }

        $translation->save();

        if (filter_var(env('FAQ_REINDEX_ON_LONG_DESCRIPTION', false), FILTER_VALIDATE_BOOL) && $pinecone) {
            $pinecone->upsertTranslation($translation);
        }

        Log::info('FAQ long_description generated', ['translation_id' => $translation->id]);
    }
}
