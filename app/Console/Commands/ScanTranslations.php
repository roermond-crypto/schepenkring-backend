<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Scans frontend JSON locale files for encoding issues, missing keys, and duplicate values.
 *
 * Usage:
 *   php artisan translations:scan          # scan all locales
 *   php artisan translations:scan fr       # scan one locale
 *   php artisan translations:scan fr --fix # auto-fix mojibake in place
 */
class ScanTranslations extends Command
{
    protected $signature = 'translations:scan
                            {locale? : Specific locale to scan (nl, en, de, fr, …)}
                            {--fix : Auto-fix Windows-1252 double-encoded mojibake in place}
                            {--json : Output results as JSON}';

    protected $description = 'Scan frontend translation JSON files for encoding, missing key, and duplicate issues';

    private const MOJIBAKE_PATTERNS = [
        '/Ã[^\s]/',      // é, è, ê, ë, à, â, ô, û, ù, î, ï, ç, etc.
        '/â€[™œ"]/',     // ', ", "
        '/Â\s/',         // non-breaking space encoded wrong
        '/Â«/',          // «
        '/Â»/',          // »
    ];

    public function handle(): int
    {
        $localesDir = base_path('../schepenkring-frontend/src/locales');

        if (! is_dir($localesDir)) {
            $this->error("Locales directory not found: {$localesDir}");
            return self::FAILURE;
        }

        $files = collect(glob("{$localesDir}/*.json"));

        if ($locale = $this->argument('locale')) {
            $files = $files->filter(fn ($f) => basename($f, '.json') === $locale);
            if ($files->isEmpty()) {
                $this->error("No locale file found for: {$locale}");
                return self::FAILURE;
            }
        }

        // Load reference locale (nl) to compare key coverage
        $reference = [];
        $refPath = "{$localesDir}/nl.json";
        if (file_exists($refPath)) {
            $reference = $this->flattenKeys(json_decode(file_get_contents($refPath), true) ?? []);
        }

        $allResults = [];
        $totalIssues = 0;

        foreach ($files->sort() as $filePath) {
            $lang = basename($filePath, '.json');
            $raw = file_get_contents($filePath);
            $data = json_decode($raw, true) ?? [];

            $mojibake = $this->detectMojibake($data);
            $flat = $this->flattenKeys($data);
            $missing = array_values(array_diff(array_keys($reference), array_keys($flat)));
            $duplicates = $this->findDuplicates($flat);

            $issueCount = count($mojibake) + count($missing) + count($duplicates);
            $totalIssues += $issueCount;

            $allResults[$lang] = [
                'file' => $filePath,
                'total_keys' => count($flat),
                'encoding_issues' => $mojibake,
                'missing_keys' => $missing,
                'duplicate_values' => $duplicates,
                'issue_count' => $issueCount,
            ];

            // Auto-fix mojibake if requested
            if ($this->option('fix') && ! empty($mojibake)) {
                $fixed = $this->fixMojibake($data);
                file_put_contents($filePath, json_encode($fixed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
                $this->info("Fixed {$lang}: " . count($mojibake) . ' encoding issues corrected.');
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($allResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $totalIssues > 0 ? self::FAILURE : self::SUCCESS;
        }

        // Human-readable output
        $this->newLine();
        $this->line('<fg=cyan;options=bold>  Translation Quality Report</>');
        $this->line('  ' . str_repeat('─', 60));

        $rows = [];
        foreach ($allResults as $lang => $res) {
            $enc = count($res['encoding_issues']);
            $miss = count($res['missing_keys']);
            $dup = count($res['duplicate_values']);
            $icon = $res['issue_count'] === 0 ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $rows[] = [
                $icon . '  ' . strtoupper($lang),
                $res['total_keys'],
                $enc > 0 ? "<fg=red>{$enc}</>" : '<fg=green>0</>',
                $miss > 0 ? "<fg=yellow>{$miss}</>" : '<fg=green>0</>',
                $dup > 0 ? "<fg=yellow>{$dup}</>" : '<fg=green>0</>',
                $res['issue_count'],
            ];
        }

        $this->table(
            ['Locale', 'Keys', 'Encoding', 'Missing', 'Duplicates', 'Total Issues'],
            $rows,
        );

        foreach ($allResults as $lang => $res) {
            if (empty($res['encoding_issues']) && empty($res['missing_keys'])) {
                continue;
            }

            $this->newLine();
            $this->line("<fg=yellow;options=bold>  {$lang}.json details:</>");

            if (! empty($res['encoding_issues'])) {
                $this->line('  <fg=red>Encoding issues (first 10):</>');
                foreach (array_slice($res['encoding_issues'], 0, 10) as $key => $value) {
                    $this->line("    • {$key}: {$value}");
                }
                if (count($res['encoding_issues']) > 10) {
                    $remaining = count($res['encoding_issues']) - 10;
                    $this->line("    … and {$remaining} more");
                }
            }

            if (! empty($res['missing_keys'])) {
                $this->line('  <fg=yellow>Missing keys (first 10):</>');
                foreach (array_slice($res['missing_keys'], 0, 10) as $key) {
                    $this->line("    • {$key}");
                }
                if (count($res['missing_keys']) > 10) {
                    $remaining = count($res['missing_keys']) - 10;
                    $this->line("    … and {$remaining} more");
                }
            }
        }

        $this->newLine();
        if ($totalIssues === 0) {
            $this->info('  ✓ All translations clean — no issues found.');
        } else {
            $this->warn("  Total issues found: {$totalIssues}");
            if (! $this->option('fix')) {
                $this->line('  Run with --fix to automatically repair encoding issues.');
            }
        }
        $this->newLine();

        return $totalIssues > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Detect strings containing Windows-1252 double-encoded characters */
    private function detectMojibake(array $data, string $prefix = ''): array
    {
        $results = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $results = array_merge($results, $this->detectMojibake($value, $fullKey));
            } elseif (is_string($value) && $this->hasMojibake($value)) {
                $results[$fullKey] = $value;
            }
        }
        return $results;
    }

    private function hasMojibake(string $s): bool
    {
        foreach (self::MOJIBAKE_PATTERNS as $pattern) {
            if (preg_match($pattern, $s)) {
                return true;
            }
        }
        return false;
    }

    /** Flatten nested keys to dot-notation */
    private function flattenKeys(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenKeys($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }

    /** Find keys that share the exact same value (potential untranslated copies) */
    private function findDuplicates(array $flat): array
    {
        $valueMap = [];
        foreach ($flat as $key => $value) {
            if (is_string($value) && strlen($value) > 3) {
                $valueMap[$value][] = $key;
            }
        }
        $dupes = [];
        foreach ($valueMap as $value => $keys) {
            if (count($keys) > 1) {
                $dupes[$value] = $keys;
            }
        }
        return $dupes;
    }

    /** Fix CP1252 double-encoded strings by re-encoding as CP1252 → UTF-8 */
    private function fixMojibake(mixed $value): mixed
    {
        if (is_string($value)) {
            try {
                $candidate = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                return $candidate !== $value ? $candidate : $value;
            } catch (\Throwable) {
                return $value;
            }
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->fixMojibake($v), $value);
        }
        return $value;
    }
}
