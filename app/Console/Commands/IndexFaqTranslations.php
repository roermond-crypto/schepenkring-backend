<?php

namespace App\Console\Commands;

use App\Models\FaqTranslation;
use App\Services\FaqPineconeService;
use Illuminate\Console\Command;

class IndexFaqTranslations extends Command
{
    protected $signature = 'faq:index {--language=} {--all} {--limit=200}';
    protected $description = 'Index FAQ translations in Pinecone';

    public function handle(FaqPineconeService $pinecone): int
    {
        $language = $this->option('language');
        $limit = (int) $this->option('limit');

        $query = FaqTranslation::with('faq');
        if (!$this->option('all')) {
            $query->whereNull('indexed_at');
        }

        if ($language) {
            $query->where('language', strtolower($language));
        }

        $translations = $query->limit($limit)->get();
        if ($translations->isEmpty()) {
            $this->info('No FAQ translations to index.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($translations as $translation) {
            if ($pinecone->upsertTranslation($translation)) {
                $count++;
            }
        }

        $this->info("Indexed {$count} FAQ translations.");
        return self::SUCCESS;
    }
}
