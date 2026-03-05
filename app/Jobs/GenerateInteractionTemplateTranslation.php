<?php

namespace App\Jobs;

use App\Models\InteractionTemplate;
use App\Services\ContentTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateInteractionTemplateTranslation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $templateId,
        public string $targetLocale,
        public bool $force = false
    ) {
    }

    public function handle(ContentTranslationService $translations): void
    {
        $template = InteractionTemplate::find($this->templateId);
        if (!$template) {
            return;
        }

        $target = strtolower($this->targetLocale);
        $result = $translations->translateInteractionTemplate($template, $target, $this->force);
        if ($result) {
            Log::info('Interaction template translation generated', [
                'template_id' => $template->id,
                'locale' => $target,
            ]);
        }
    }
}
