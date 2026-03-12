<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

class FaqKnowledgeTextExtractor
{
    public function extract(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'txt', 'md' => $this->normalize((string) file_get_contents($file->getRealPath())),
            'csv' => $this->extractCsv($file),
            'docx' => $this->extractDocx($file),
            'xlsx' => $this->extractXlsx($file),
            'pdf' => $this->extractPdf($file),
            default => throw new RuntimeException('Unsupported knowledge file type: '.$extension),
        };
    }

    private function extractCsv(UploadedFile $file): string
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'rb');

        if (! $handle) {
            throw new RuntimeException('Unable to read CSV file.');
        }

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $values = array_values(array_filter(array_map(
                    static fn ($value) => is_string($value) ? trim($value) : null,
                    $row
                )));

                if ($values !== []) {
                    $rows[] = implode(' | ', $values);
                }
            }
        } finally {
            fclose($handle);
        }

        return $this->normalize(implode("\n", $rows));
    }

    private function extractDocx(UploadedFile $file): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('Unable to open Word document.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            throw new RuntimeException('Word document text is empty.');
        }

        return $this->normalize(html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml))));
    }

    private function extractXlsx(UploadedFile $file): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('Unable to open Excel workbook.');
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedStringsXml) && $sharedStringsXml !== '') {
            $shared = @simplexml_load_string($sharedStringsXml);
            if ($shared !== false) {
                foreach ($shared->si ?? [] as $item) {
                    $parts = [];
                    foreach ($item->xpath('.//t') ?: [] as $node) {
                        $parts[] = (string) $node;
                    }
                    $sharedStrings[] = trim(implode('', $parts));
                }
            }
        }

        $rows = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || ! str_starts_with($name, 'xl/worksheets/') || ! str_ends_with($name, '.xml')) {
                continue;
            }

            $sheetXml = $zip->getFromIndex($i);
            if (! is_string($sheetXml) || $sheetXml === '') {
                continue;
            }

            $sheet = @simplexml_load_string($sheetXml);
            if ($sheet === false) {
                continue;
            }

            foreach ($sheet->sheetData->row ?? [] as $row) {
                $values = [];
                foreach ($row->c ?? [] as $cell) {
                    $type = (string) ($cell['t'] ?? '');
                    if ($type === 'inlineStr') {
                        $values[] = trim((string) ($cell->is->t ?? ''));
                        continue;
                    }

                    $value = trim((string) ($cell->v ?? ''));
                    if ($value === '') {
                        continue;
                    }

                    if ($type === 's' && isset($sharedStrings[(int) $value])) {
                        $values[] = $sharedStrings[(int) $value];
                    } else {
                        $values[] = $value;
                    }
                }

                $values = array_values(array_filter($values, static fn ($value) => $value !== ''));
                if ($values !== []) {
                    $rows[] = implode(' | ', $values);
                }
            }
        }

        $zip->close();

        return $this->normalize(implode("\n", $rows));
    }

    private function extractPdf(UploadedFile $file): string
    {
        $contents = (string) file_get_contents($file->getRealPath());
        if ($contents === '') {
            throw new RuntimeException('Unable to read PDF file.');
        }

        $texts = [];
        preg_match_all('/stream(.*?)endstream/s', $contents, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $stream = ltrim((string) ($match[1] ?? ''), "\r\n");
            $decoded = $this->decodePdfStream($stream);
            $candidate = $decoded ?: $stream;

            preg_match_all('/\((.*?)\)\s*Tj/s', $candidate, $directTexts);
            foreach ($directTexts[1] ?? [] as $text) {
                $texts[] = $this->decodePdfText((string) $text);
            }

            preg_match_all('/\[(.*?)\]\s*TJ/s', $candidate, $arrayTexts);
            foreach ($arrayTexts[1] ?? [] as $segment) {
                preg_match_all('/\((.*?)\)/s', (string) $segment, $parts);
                foreach ($parts[1] ?? [] as $text) {
                    $texts[] = $this->decodePdfText((string) $text);
                }
            }
        }

        $text = $this->normalize(implode("\n", array_filter($texts)));
        if ($text === '') {
            throw new RuntimeException('Unable to extract readable text from PDF.');
        }

        return $text;
    }

    private function decodePdfStream(string $stream): ?string
    {
        $attempts = [
            static fn (string $value) => @gzuncompress($value) ?: null,
            static fn (string $value) => @gzinflate($value) ?: null,
            static fn (string $value) => @gzdecode($value) ?: null,
        ];

        foreach ($attempts as $attempt) {
            $decoded = $attempt($stream);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return null;
    }

    private function decodePdfText(string $text): string
    {
        $text = str_replace(['\\(', '\\)', '\\n', '\\r', '\\t'], ['(', ')', "\n", "\n", ' '], $text);

        return preg_replace('/\\\\(\d{3})/', '', $text) ?? $text;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
