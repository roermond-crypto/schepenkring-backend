<?php

namespace App\Services;

use App\Jobs\GenerateFaqLongDescription;
use App\Models\FaqEntry;
use App\Models\FaqTranslation;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FaqImportService
{
    public function import(string $filePath, array $options = []): array
    {
        $defaults = [
            'default_language' => 'nl',
            'generate_long_descriptions' => false,
            'batch_size' => (int) env('FAQ_GENERATION_BATCH_SIZE', 50),
            'return_translation_ids' => false,
        ];

        $options = array_merge($defaults, $options);

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows)) {
            return ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $headerRow = array_shift($rows);
        $headerMap = $this->mapHeaders($headerRow);

        if (!$this->hasRequiredHeaders($headerMap)) {
            // Header row is likely actual data; fall back to default column mapping.
            array_unshift($rows, $headerRow);
            $headerMap = $this->defaultHeaderMap($headerRow);
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $pendingLongDescriptions = [];
        $touchedTranslationIds = [];

        foreach ($rows as $row) {
            $data = $this->extractRow($row, $headerMap);

            if (empty($data['question']) || empty($data['answer'])) {
                $skipped++;
                continue;
            }

            $language = $data['language'] ?: $options['default_language'];
            $slug = $data['slug'] ?: $this->buildSlug($data);

            if (!$slug) {
                $skipped++;
                continue;
            }

            $namespace = $data['namespace'] ?: 'core';

            $faq = FaqEntry::updateOrCreate(
                ['slug' => $slug],
                [
                    'category' => $data['category'],
                    'subcategory' => $data['subcategory'],
                    'namespace' => $namespace,
                    'is_active' => true,
                ]
            );

            $existing = FaqTranslation::where('faq_id', $faq->id)
                ->where('language', $language)
                ->first();

            $attributes = [
                'question' => $data['question'],
                'answer' => $data['answer'],
                'long_description' => $data['long_description'],
                'source_language' => $language,
                'needs_review' => false,
            ];

            if (!$existing) {
                $translation = FaqTranslation::create(array_merge($attributes, [
                    'faq_id' => $faq->id,
                    'language' => $language,
                    'long_description_status' => empty($data['long_description']) ? 'pending' : 'ready',
                    'indexed_at' => null,
                ]));
                $imported++;
            } else {
                $changed = $existing->question !== $attributes['question'] || $existing->answer !== $attributes['answer'];
                $existing->fill($attributes);
                if ($changed) {
                    $existing->indexed_at = null;
                }
                if (empty($existing->long_description)) {
                    $existing->long_description_status = $existing->long_description_status === 'ready'
                        ? $existing->long_description_status
                        : 'pending';
                } elseif ($existing->long_description_status !== 'ready') {
                    $existing->long_description_status = 'ready';
                }
                $existing->save();
                $translation = $existing;
                $updated++;
            }

            if ($options['generate_long_descriptions'] && empty($translation->long_description)) {
                $pendingLongDescriptions[] = $translation->id;
            }

            if ($options['return_translation_ids']) {
                $touchedTranslationIds[] = $translation->id;
            }
        }

        if ($options['generate_long_descriptions'] && !empty($pendingLongDescriptions)) {
            $batchSize = max(1, (int) $options['batch_size']);
            foreach (array_chunk($pendingLongDescriptions, $batchSize) as $chunk) {
                foreach ($chunk as $translationId) {
                    GenerateFaqLongDescription::dispatch($translationId);
                }
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'translation_ids' => $options['return_translation_ids'] ? $touchedTranslationIds : [],
        ];
    }

    private function mapHeaders(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $column => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            $map[$column] = $normalized;
        }

        return $map;
    }

    private function hasRequiredHeaders(array $headerMap): bool
    {
        $values = array_filter(array_values($headerMap));
        $required = ['question', 'answer'];
        $presentRequired = count(array_intersect($required, $values));
        $recognized = count(array_intersect(
            ['category', 'subcategory', 'namespace', 'question', 'answer', 'long_description', 'language', 'lang', 'slug'],
            $values
        ));

        return $presentRequired >= 2 && $recognized >= 3;
    }

    private function defaultHeaderMap(array $row): array
    {
        $columns = array_keys($row);
        $defaultOrder = [
            'category',
            'subcategory',
            'question',
            'answer',
            'language',
            'namespace',
            'slug',
            'long_description',
        ];

        $map = [];
        foreach ($columns as $index => $column) {
            $map[$column] = $defaultOrder[$index] ?? null;
        }

        return $map;
    }

    private function extractRow(array $row, array $headerMap): array
    {
        $data = [
            'category' => null,
            'subcategory' => null,
            'namespace' => null,
            'question' => null,
            'answer' => null,
            'long_description' => null,
            'language' => null,
            'slug' => null,
        ];

        foreach ($row as $column => $value) {
            $key = $headerMap[$column] ?? null;
            if (!$key) {
                continue;
            }

            $clean = $this->normalizeValue($value);

            switch ($key) {
                case 'category':
                case 'subcategory':
                case 'namespace':
                case 'question':
                case 'answer':
                case 'long_description':
                case 'slug':
                    $data[$key] = $clean;
                    break;
                case 'language':
                case 'lang':
                    $data['language'] = strtolower($clean);
                    break;
                default:
                    break;
            }
        }

        return $data;
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/i', '_', $header);
        $header = trim((string) $header, '_');

        $aliases = [
            'vraag' => 'question',
            'vraagtekst' => 'question',
            'question_nl' => 'question',
            'antwoord' => 'answer',
            'antwoordtekst' => 'answer',
            'answer_nl' => 'answer',
            'categorie' => 'category',
            'subcategorie' => 'subcategory',
            'sub_categorie' => 'subcategory',
            'taal' => 'language',
            'lang' => 'language',
            'lange_beschrijving' => 'long_description',
            'longdescription' => 'long_description',
            'long_description' => 'long_description',
            'description_long' => 'long_description',
        ];

        return $aliases[$header] ?? $header;
    }

    private function normalizeValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return Str::squish($value);
    }

    private function buildSlug(array $data): ?string
    {
        $namespace = $data['namespace'] ?? 'core';
        $parts = array_filter([
            $namespace,
            $data['category'] ?? null,
            $data['subcategory'] ?? null,
            $data['question'] ?? null,
        ]);

        if (empty($parts)) {
            return null;
        }

        return Str::slug(implode(' ', $parts));
    }
}
