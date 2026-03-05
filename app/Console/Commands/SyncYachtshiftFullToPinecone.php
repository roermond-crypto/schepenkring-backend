<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class SyncYachtshiftFullToPinecone extends Command
{
    protected $signature = 'yachtshift:sync-full-pinecone
        {--url=* : Feed URL(s). If omitted, uses config services.yachtshift.feed_urls}
        {--file= : Path to a local XML file}
        {--dir= : Path to a directory containing XML files}
        {--namespace= : Pinecone namespace}
        {--limit=0 : Max boats to process (0 = no limit)}
        {--batch=25 : Upsert batch size}
        {--dry-run : Parse and embed only, skip Pinecone upsert}';

    protected $description = 'Sync all boats from Yachtshift feeds or local XML files to Pinecone and store the full boat payload without dropping fields.';

    public function handle(): int
    {
        ini_set('memory_limit', '512M'); // Increase limit to parse large XMLs

        $openAiKey = (string) config('services.openai.key');
        $pineconeKey = (string) config('services.pinecone.key');
        $pineconeHost = (string) config('services.pinecone.host');
        $namespace = $this->option('namespace');
        $namespace = is_string($namespace) && $namespace !== '' ? $namespace : null;
        $batchSize = max(1, (int) $this->option('batch'));
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $feedUrls = $this->resolveFeedUrls();
        if (empty($feedUrls)) {
            $this->error('No feed URLs/files provided. Set --url, --file, or --dir');
            return self::FAILURE;
        }

        if ($openAiKey === '') {
            $this->error('Missing OPENAI_API_KEY');
            return self::FAILURE;
        }

        if (!$dryRun && ($pineconeKey === '' || $pineconeHost === '')) {
            $this->error('Missing PINECONE_API_KEY or PINECONE_HOST');
            return self::FAILURE;
        }

        $pineconeUrl = str_starts_with($pineconeHost, 'http')
            ? rtrim($pineconeHost, '/') . '/vectors/upsert'
            : 'https://' . trim($pineconeHost, '/') . '/vectors/upsert';

        $this->info('Starting full-field Yachtshift -> Pinecone sync');
        $this->line('Feeds/Files: ' . count($feedUrls) . ', batch: ' . $batchSize . ', dry-run: ' . ($dryRun ? 'yes' : 'no'));

        $vectors = [];
        $processed = 0;
        $indexed = 0;
        $failed = 0;

        foreach ($feedUrls as $feedUrl) {
            $this->info("Fetching feed/file: {$feedUrl}");
            $adverts = $this->fetchAdverts($feedUrl);

            if (empty($adverts)) {
                $this->warn("No adverts found for feed/file: {$feedUrl}");
                continue;
            }

            foreach ($adverts as $advertIndex => $advert) {
                $this->info("Processing advert index $advertIndex...");
                if ($limit > 0 && $processed >= $limit) {
                    break 2;
                }

                $processed++;
                $ref = trim((string) ($advert['@attributes']['ref'] ?? ''));
                if ($ref === '') {
                    $ref = 'no_ref_' . ($advertIndex + 1);
                }

                $record = [
                    'source_feed_url' => $feedUrl,
                    'boat_ref' => $ref,
                    'synced_at_utc' => now()->toIso8601String(),
                    'advert' => $advert,
                ];

                $fullJson = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($fullJson === false) {
                    $failed++;
                    $this->error("Failed to JSON encode boat {$ref}");
                    continue;
                }

                $this->info("Embedding boat $ref ($advertIndex)");
                $embeddingInput = $this->buildEmbeddingInput($record, $fullJson);
                $vector = $this->createEmbedding($openAiKey, $embeddingInput);
                if ($vector === null) {
                    $failed++;
                    $this->error("Embedding failed for boat {$ref}");
                    continue;
                }

                $compressedPayload = base64_encode(gzencode($fullJson, 9) ?: $fullJson);
                $metadata = [
                    'boat_ref' => $ref,
                    'source_feed_url' => $feedUrl,
                    'synced_at_utc' => now()->toIso8601String(),
                    'full_payload_gzip_b64' => $compressedPayload,
                    'full_payload_sha256' => hash('sha256', $fullJson),
                    'full_payload_size' => strlen($fullJson),
                    'field_count' => $this->countFields($advert),
                ];

                $vectors[] = [
                    'id' => 'ys_' . substr(hash('sha256', $feedUrl . '|' . $ref), 0, 48),
                    'values' => $vector,
                    'metadata' => $metadata,
                ];

                if (count($vectors) >= $batchSize) {
                    if ($dryRun) {
                        $indexed += count($vectors);
                        $vectors = [];
                        continue;
                    }

                    $ok = $this->upsertBatch($pineconeUrl, $pineconeKey, $vectors, $namespace);
                    if ($ok) {
                        $indexed += count($vectors);
                    } else {
                        $failed += count($vectors);
                    }
                    $vectors = [];
                }
            }
        }

        if (!empty($vectors)) {
            if ($dryRun) {
                $indexed += count($vectors);
            } else {
                $ok = $this->upsertBatch($pineconeUrl, $pineconeKey, $vectors, $namespace);
                if ($ok) {
                    $indexed += count($vectors);
                } else {
                    $failed += count($vectors);
                }
            }
        }

        $this->info("Done. Processed: {$processed}, Indexed: {$indexed}, Failed: {$failed}");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveFeedUrls(): array
    {
        $filePath = $this->option('file');
        $dirPath = $this->option('dir');
        
        $urls = [];
        if ($filePath) {
            $urls[] = $filePath;
        } elseif ($dirPath) {
            if (is_dir($dirPath)) {
                $files = glob(rtrim($dirPath, '/') . '/*.xml');
                if ($files !== false) {
                    $urls = array_merge($urls, $files);
                }
            }
        }

        $fromOption = (array) $this->option('url');
        $fromOption = array_values(array_filter(array_map('trim', $fromOption)));
        $urls = array_merge($urls, $fromOption);
        
        if (!empty($urls)) {
            return $urls;
        }

        $fromConfig = config('services.yachtshift.feed_urls', []);
        if (!is_array($fromConfig)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($url) => trim((string) $url), $fromConfig)));
    }

    private function fetchAdverts(string $feedUrl)
    {
        try {
            if (file_exists($feedUrl)) {
                $this->info("Accessing local file: $feedUrl");
                $xmlPath = $feedUrl;
            } else {
                $this->info("Accessing remote URL: $feedUrl");
                $xmlPath = tempnam(sys_get_temp_dir(), 'yachtshift_');
                $response = Http::timeout(90)->get($feedUrl);
                if (!$response->successful()) {
                    $this->error("Feed request failed ({$response->status()}) for {$feedUrl}");
                    return;
                }
                file_put_contents($xmlPath, $response->body());
            }

            $this->info("Opening XMLReader for: $xmlPath");
            $reader = new \XMLReader();
            if (!$reader->open($xmlPath)) {
                $this->error("Failed to open XML: {$feedUrl}");
                return;
            }

            // Optional: Expand memory usage during reading
            while ($reader->read()) {
                if ($reader->nodeType == \XMLReader::ELEMENT && $reader->name == 'advert') {
                    $outerXML = $reader->readOuterXml();
                    $node = simplexml_load_string($outerXML, SimpleXMLElement::class, LIBXML_NOCDATA);
                    if ($node) {
                        yield $this->xmlNodeToArray($node);
                    }
                }
            }
            $this->info("Finished reading XML file");
            $reader->close();
            
            if (!file_exists($feedUrl) && file_exists($xmlPath)) {
                unlink($xmlPath);
            }

        } catch (\Throwable $e) {
            Log::error('Yachtshift feed fetch failed', ['url' => $feedUrl, 'error' => $e->getMessage()]);
            $this->error("Exception while fetching feed/file {$feedUrl}: {$e->getMessage()}");
        }
    }

    private function xmlNodeToArray(SimpleXMLElement $node): array
    {
        $result = [];

        foreach ($node->attributes() as $key => $value) {
            $result['@attributes'][(string) $key] = trim((string) $value);
        }

        $children = $node->children();
        if (count($children) === 0) {
            $value = trim((string) $node);
            if ($value !== '') {
                $result['@value'] = $value;
            }
            return $result;
        }

        $grouped = [];
        foreach ($children as $childName => $child) {
            $grouped[$childName][] = $this->xmlNodeToArray($child);
        }

        foreach ($grouped as $name => $items) {
            $result[$name] = count($items) === 1 ? $items[0] : $items;
        }

        $text = trim((string) $node);
        if ($text !== '') {
            $result['@value'] = $text;
        }

        return $result;
    }

    private function buildEmbeddingInput(array $record, string $fullJson): string
    {
        $head = "Boat ref: " . ($record['boat_ref'] ?? 'unknown') . "\n";
        $head .= "Source: " . ($record['source_feed_url'] ?? 'unknown') . "\n";
        $head .= "All fields JSON:\n";
        return $head . $fullJson;
    }

    private function createEmbedding(string $openAiKey, string $input): ?array
    {
        try {
            $chunks = $this->splitForEmbedding($input, 6000);
            $vectors = [];

            foreach ($chunks as $chunk) {
                $response = Http::withToken($openAiKey)
                    ->timeout(90)
                    ->post('https://api.openai.com/v1/embeddings', [
                        'model' => 'text-embedding-3-small',
                        'input' => $chunk,
                        'dimensions' => 1408,
                    ]);

                if (!$response->successful()) {
                    Log::error('OpenAI embedding request failed', ['status' => $response->status(), 'body' => $response->body()]);
                    return null;
                }

                $vector = $response->json('data.0.embedding');
                if (!is_array($vector)) {
                    return null;
                }
                $vectors[] = $vector;
            }

            if (empty($vectors)) {
                return null;
            }

            if (count($vectors) === 1) {
                return $vectors[0];
            }

            $dimensions = count($vectors[0]);
            $averaged = array_fill(0, $dimensions, 0.0);
            $count = count($vectors);

            foreach ($vectors as $vector) {
                for ($i = 0; $i < $dimensions; $i++) {
                    $averaged[$i] += (float) $vector[$i];
                }
            }

            for ($i = 0; $i < $dimensions; $i++) {
                $averaged[$i] /= $count;
            }

            return $averaged;
        } catch (\Throwable $e) {
            Log::error('OpenAI embedding exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function splitForEmbedding(string $text, int $maxCharsPerChunk): array
    {
        if (mb_strlen($text) <= $maxCharsPerChunk) {
            return [$text];
        }

        $chunks = [];
        $offset = 0;
        $length = mb_strlen($text);

        while ($offset < $length) {
            $chunks[] = mb_substr($text, $offset, $maxCharsPerChunk);
            $offset += $maxCharsPerChunk;
        }

        return $chunks;
    }

    private function upsertBatch(string $pineconeUrl, string $pineconeKey, array $vectors, ?string $namespace): bool
    {
        try {
            $payload = ['vectors' => $vectors];
            if ($namespace !== null) {
                $payload['namespace'] = $namespace;
            }

            $response = Http::withHeaders([
                'Api-Key' => $pineconeKey,
                'Content-Type' => 'application/json',
            ])->timeout(90)->post($pineconeUrl, $payload);

            if (!$response->successful()) {
                Log::error('Pinecone upsert failed', ['status' => $response->status(), 'body' => $response->body()]);
                $this->error('Pinecone upsert failed: HTTP ' . $response->status());
                return false;
            }

            $this->line('Upserted batch of ' . count($vectors) . ' boats');
            return true;
        } catch (\Throwable $e) {
            Log::error('Pinecone upsert exception', ['error' => $e->getMessage()]);
            $this->error('Pinecone upsert exception: ' . $e->getMessage());
            return false;
        }
    }

    private function countFields(array $data): int
    {
        $count = 0;
        $walker = function ($value) use (&$count, &$walker) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $count++;
                    $walker($v);
                }
            }
        };
        $walker($data);
        return $count;
    }
}
