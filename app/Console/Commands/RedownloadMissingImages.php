<?php

namespace App\Console\Commands;

use App\Models\Yacht;
use App\Models\YachtImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class RedownloadMissingImages extends Command
{
    private const MAX_IMAGE_BYTES = 12_000_000;

    protected $signature = 'app:redownload-missing-images
        {--source= : Filter by yacht source (e.g. schepenkring_sold_archive)}
        {--yacht-id= : Re-download for a single yacht ID only}
        {--dry-run : Report missing files without downloading}
        {--limit= : Max number of yachts to process}';

    protected $description = 'Find yacht images whose local file is missing on disk and re-download from the original listing page';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $limit    = $this->option('limit') ? (int) $this->option('limit') : null;
        $source   = $this->option('source');
        $yachtId  = $this->option('yacht-id') ? (int) $this->option('yacht-id') : null;

        $this->info($isDryRun ? '[DRY RUN] Scanning for missing image files…' : 'Scanning for missing image files…');

        // ── 1. Find all local-path images whose file is missing on disk ──────
        $query = YachtImage::query()
            ->with('yacht:id,external_url,source,source_identifier')
            ->whereNotNull('url')
            ->where('url', 'not like', 'http://%')
            ->where('url', 'not like', 'https://%');

        if ($yachtId) {
            $query->where('yacht_id', $yachtId);
        }

        if ($source) {
            $query->whereHas('yacht', fn ($q) => $q->where('source', $source));
        }

        $missingByYacht = [];
        $totalImages    = 0;

        $query->chunkById(500, function ($images) use (&$missingByYacht, &$totalImages) {
            foreach ($images as $image) {
                if (!Storage::disk('public')->exists($image->url)) {
                    $totalImages++;
                    $missingByYacht[$image->yacht_id][] = $image;
                }
            }
        });

        $yachtCount = count($missingByYacht);
        $this->info("Found {$totalImages} missing image file(s) across {$yachtCount} yacht(s).");

        if ($totalImages === 0 || $isDryRun) {
            if ($totalImages > 0) {
                $this->table(['Yacht ID', 'Source', 'Missing count', 'External URL'], array_map(function ($yachtId, $images) {
                    $yacht = $images[0]->yacht;
                    return [
                        $yachtId,
                        $yacht?->source ?? '—',
                        count($images),
                        $yacht?->external_url ?? '—',
                    ];
                }, array_keys($missingByYacht), array_values($missingByYacht)));
            }
            return self::SUCCESS;
        }

        // ── 2. Re-download missing files yacht by yacht ───────────────────────
        $processed    = 0;
        $downloadedOk = 0;
        $failed       = 0;

        foreach ($missingByYacht as $yachtId => $missingImages) {
            if ($limit && $processed >= $limit) {
                break;
            }

            $yacht = $missingImages[0]->yacht;
            $this->line("  Yacht #{$yachtId} — {$yacht?->external_url}");

            if (!$yacht?->external_url) {
                $this->warn("    No external_url, skipping.");
                $failed += count($missingImages);
                $processed++;
                continue;
            }

            $sourceImages = $this->scrapeImageUrls((string) $yacht->external_url);

            if (empty($sourceImages)) {
                $this->warn("    Could not scrape image URLs from listing page, skipping.");
                $failed += count($missingImages);
                $processed++;
                continue;
            }

            // Match missing DB images (by sort_order) to scraped URLs in order.
            // Sort missing images by sort_order so index aligns with scraped list.
            usort($missingImages, fn ($a, $b) => $a->sort_order <=> $b->sort_order);

            foreach ($missingImages as $image) {
                $sourceUrl = $sourceImages[$image->sort_order] ?? null;

                if (!$sourceUrl) {
                    $this->warn("    Image #{$image->id} sort_order={$image->sort_order}: no matching source URL.");
                    $failed++;
                    continue;
                }

                $ok = $this->redownloadToPath($sourceUrl, $image->url);

                if ($ok) {
                    $this->line("    ✓ Image #{$image->id} restored from {$sourceUrl}");
                    $downloadedOk++;
                } else {
                    $this->warn("    ✗ Image #{$image->id} failed to download from {$sourceUrl}");
                    $failed++;
                }
            }

            $processed++;
        }

        $this->info("Done. Restored: {$downloadedOk}, Failed: {$failed}.");
        return self::SUCCESS;
    }

    // ── Scrape image URLs from a schepenkring.nl or similar listing page ──────

    private function scrapeImageUrls(string $pageUrl): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NauticSecureBot/1.0)'])
                ->get($pageUrl);

            if (!$response->successful()) {
                return [];
            }

            $crawler = new Crawler($response->body(), $pageUrl);

            $urls = $crawler->filter('img[src*="/previews/"], img[src*="/uploads/"]')
                ->each(fn (Crawler $node) => $this->normalizeUrl($node->attr('src'), $pageUrl));

            return collect($urls)->filter()->unique()->values()->all();
        } catch (\Throwable $e) {
            Log::warning("[RedownloadMissingImages] Failed scraping {$pageUrl}: {$e->getMessage()}");
            return [];
        }
    }

    // ── Download from $sourceUrl and store at the exact $targetPath ──────────

    private function redownloadToPath(string $sourceUrl, string $targetPath): bool
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'redownload-');
        if ($tempFile === false) {
            return false;
        }

        try {
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->withOptions(['sink' => $tempFile])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; NauticSecureBot/1.0)'])
                ->get($sourceUrl);

            if (!$response->successful()) {
                return false;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if (!str_contains($contentType, 'image/')) {
                return false;
            }

            $fileSize = @filesize($tempFile);
            if (!$fileSize || $fileSize <= 0 || $fileSize > self::MAX_IMAGE_BYTES) {
                return false;
            }

            $stream = fopen($tempFile, 'rb');
            if ($stream === false) {
                return false;
            }

            // Ensure the directory exists on the public disk
            $dir = dirname($targetPath);
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }

            Storage::disk('public')->put($targetPath, $stream);
            fclose($stream);

            return Storage::disk('public')->exists($targetPath);
        } catch (\Throwable $e) {
            Log::warning("[RedownloadMissingImages] Failed downloading {$sourceUrl}: {$e->getMessage()}");
            return false;
        } finally {
            if (is_string($tempFile) && is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function normalizeUrl(string $url, string $baseUrl): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        $parts = parse_url($baseUrl);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (str_starts_with($url, '/')) {
            return $origin . $url;
        }
        $basePath = rtrim(dirname($parts['path'] ?? '/'), '/');
        return $origin . ($basePath ? $basePath . '/' : '/') . ltrim($url, '/');
    }
}
