<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class FaqKnowledgeTextExtractor
{
    private const OPENXML_SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function extract(UploadedFile $file): string
    {
        return $this->extractFromPath(
            $file->getRealPath(),
            $file->getClientOriginalExtension(),
        );
    }

    public function extractFromPath(string $path, ?string $extension = null): string
    {
        $extension = strtolower($extension ?: pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'txt', 'md' => $this->normalize((string) file_get_contents($path)),
            'csv' => $this->extractCsv($path),
            'docx' => $this->extractDocx($path),
            'xlsx' => $this->extractXlsx($path),
            'pdf' => $this->extractPdf($path),
            default => throw new RuntimeException('Unsupported knowledge file type: '.$extension),
        };
    }

    private function extractCsv(string $path): string
    {
        $rows = [];
        $handle = fopen($path, 'rb');

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

    private function extractDocx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open Word document.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            throw new RuntimeException('Word document text is empty.');
        }

        return $this->normalize(html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml))));
    }

    private function extractXlsx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open Excel workbook.');
        }

        try {
            $sharedStrings = $this->extractXlsxSharedStrings($zip);
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

                array_push($rows, ...$this->extractXlsxRows($sheetXml, $sharedStrings));
            }

            return $this->normalize(implode("\n", $rows));
        } finally {
            $zip->close();
        }
    }

    private function extractPdf(string $path): string
    {
        $contents = (string) file_get_contents($path);
        if ($contents === '') {
            throw new RuntimeException('Unable to read PDF file.');
        }

        $texts = [];
        preg_match_all('/stream(.*?)endstream/s', $contents, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $stream = ltrim((string) ($match[1] ?? ''), "\r\n");
            $decoded = $this->decodePdfStream($stream);
            $candidate = $decoded ?: $stream;
            $streamTexts = [];

            preg_match_all('/\((.*?)\)\s*Tj/s', $candidate, $directTexts);
            foreach ($directTexts[1] ?? [] as $text) {
                $streamTexts[] = $this->decodePdfText((string) $text);
            }

            preg_match_all('/\[(.*?)\]\s*TJ/s', $candidate, $arrayTexts);
            foreach ($arrayTexts[1] ?? [] as $segment) {
                preg_match_all('/\((.*?)\)/s', (string) $segment, $parts);
                foreach ($parts[1] ?? [] as $text) {
                    $streamTexts[] = $this->decodePdfText((string) $text);
                }
            }

            $streamTexts = array_values(array_filter($streamTexts, static fn (string $value) => trim($value) !== ''));

            if ($streamTexts === []) {
                $fallbackText = $this->extractPdfFallbackText($candidate);
                if ($fallbackText !== '') {
                    $streamTexts[] = $fallbackText;
                }
            }

            array_push($texts, ...$streamTexts);
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
        $text = str_replace(['\\\\', '\\(', '\\)', '\\n', '\\r', '\\t'], ['\\', '(', ')', "\n", "\n", ' '], $text);
        $text = preg_replace_callback('/\\\\([0-7]{3})/', static function (array $matches): string {
            return chr(octdec($matches[1]));
        }, $text) ?? $text;

        if (str_starts_with($text, "\xFE\xFF")) {
            $decoded = @mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');

            return is_string($decoded) ? $decoded : $text;
        }

        if (str_starts_with($text, "\xFF\xFE")) {
            $decoded = @mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16LE');

            return is_string($decoded) ? $decoded : $text;
        }

        return $text;
    }

    /**
     * @return array<int, string>
     */
    private function extractXlsxSharedStrings(ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if (! is_string($sharedStringsXml) || $sharedStringsXml === '') {
            return [];
        }

        $sharedStrings = $this->loadXml($sharedStringsXml);
        if (! $sharedStrings) {
            return [];
        }

        $values = [];

        foreach ($this->xmlNodes($sharedStrings, '//x:si') as $item) {
            $values[] = $this->extractXmlText($item);
        }

        return $values;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, string>
     */
    private function extractXlsxRows(string $sheetXml, array $sharedStrings): array
    {
        $sheet = $this->loadXml($sheetXml);
        if (! $sheet) {
            return [];
        }

        $rows = [];

        foreach ($this->xmlNodes($sheet, '//x:sheetData/x:row') as $row) {
            $values = [];

            foreach ($this->xmlNodes($row, './x:c') as $cell) {
                $value = $this->extractXlsxCellValue($cell, $sharedStrings);
                if ($value !== '') {
                    $values[] = $value;
                }
            }

            if ($values !== []) {
                $rows[] = implode(' | ', $values);
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function extractXlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return $this->extractXmlText($cell);
        }

        $value = trim($this->xmlNodeText($cell, './x:v'));
        if ($value === '') {
            return $this->extractXmlText($cell);
        }

        return match ($type) {
            's' => $sharedStrings[(int) $value] ?? '',
            'b' => $value === '1' ? 'TRUE' : 'FALSE',
            default => $value,
        };
    }

    private function extractPdfFallbackText(string $stream): string
    {
        $sanitized = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $stream) ?? '';
        $lines = preg_split("/\r\n|\r|\n/", $sanitized) ?: [];
        $fragments = [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
            if ($line === '' || ! preg_match('/[A-Za-z]{3,}/', $line)) {
                continue;
            }

            $fragments[] = $line;
        }

        return implode("\n", $fragments);
    }

    private function loadXml(string $xml): ?SimpleXMLElement
    {
        $document = @simplexml_load_string($xml);

        return $document instanceof SimpleXMLElement ? $document : null;
    }

    /**
     * @return array<int, SimpleXMLElement>
     */
    private function xmlNodes(SimpleXMLElement $element, string $expression): array
    {
        $element->registerXPathNamespace('x', $this->xmlNamespace($element));

        return $element->xpath($expression) ?: [];
    }

    private function xmlNodeText(SimpleXMLElement $element, string $expression): string
    {
        $nodes = $this->xmlNodes($element, $expression);

        return isset($nodes[0]) ? (string) $nodes[0] : '';
    }

    private function extractXmlText(SimpleXMLElement $element): string
    {
        $parts = [];

        foreach ($this->xmlNodes($element, './/x:t') as $node) {
            $parts[] = (string) $node;
        }

        return trim(implode('', $parts));
    }

    private function xmlNamespace(SimpleXMLElement $element): string
    {
        $namespaces = $element->getDocNamespaces(true);

        return $namespaces[''] ?? self::OPENXML_SPREADSHEET_NAMESPACE;
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
