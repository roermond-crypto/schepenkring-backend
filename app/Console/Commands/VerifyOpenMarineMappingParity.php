<?php

namespace App\Console\Commands;

use App\Models\Yacht;
use App\Services\OpenMarineService;
use Illuminate\Console\Command;

/**
 * Diffs the new data-driven OpenMarineService::buildXml() against the
 * preserved-verbatim legacy hardcoded implementation (buildXmlLegacy())
 * for a sample of real yachts. Run this before removing buildXmlLegacy() —
 * it could not be executed during development (no working database driver
 * in that environment), so the rewrite was only verified by manual
 * line-by-line comparison against the original source.
 */
class VerifyOpenMarineMappingParity extends Command
{
    protected $signature = 'openmarine:verify-mapping-parity {--limit=25 : Number of most-recently-updated yachts to check}';

    protected $description = 'Diff the new data-driven OpenMarine XML generator against the preserved legacy implementation.';

    public function handle(OpenMarineService $service): int
    {
        $limit = (int) $this->option('limit');
        $yachts = Yacht::query()->latest('updated_at')->limit($limit)->get();

        if ($yachts->isEmpty()) {
            $this->warn('No yachts found to check.');

            return self::SUCCESS;
        }

        $reflection = new \ReflectionMethod($service, 'buildXml');
        $reflection->setAccessible(true);

        $mismatches = 0;

        foreach ($yachts as $yacht) {
            $new = $reflection->invoke($service, $yacht);
            $legacy = $service->buildXmlLegacy($yacht);

            if ($this->normalize($new) === $this->normalize($legacy)) {
                continue;
            }

            $mismatches++;
            $this->error("Mismatch for yacht #{$yacht->id} ({$yacht->boat_name})");
            $this->line('--- legacy ---');
            $this->line($legacy);
            $this->line('--- new ---');
            $this->line($new);
            $this->newLine();
        }

        if ($mismatches === 0) {
            $this->info("Parity confirmed across {$yachts->count()} yachts — no differences.");

            return self::SUCCESS;
        }

        $this->error("{$mismatches} of {$yachts->count()} yachts differ. Do not remove buildXmlLegacy() until these are resolved.");

        return self::FAILURE;
    }

    /**
     * Reparse-and-reformat so only structural differences are reported,
     * not incidental whitespace.
     */
    private function normalize(string $xml): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $dom->formatOutput = true;

        return $dom->saveXML();
    }
}
