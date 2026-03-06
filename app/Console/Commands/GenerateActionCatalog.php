<?php

namespace App\Console\Commands;

use App\Services\CopilotActionCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateActionCatalog extends Command
{
    protected $signature = 'copilot:generate-action-catalog {--path=action-catalog.json}';
    protected $description = 'Generate the Copilot action catalog JSON file.';

    public function handle(CopilotActionCatalogService $catalogService): int
    {
        $path = (string) $this->option('path');
        $catalog = $catalogService->buildCatalog();

        Storage::put($path, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Action catalog written to storage/' . ltrim($path, '/'));

        return self::SUCCESS;
    }
}
