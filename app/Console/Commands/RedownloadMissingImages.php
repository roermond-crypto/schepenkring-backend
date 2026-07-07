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
            $this->line("  Yacht #{$yachtId} — {$yacht?->source_identifier} ({$yacht?->source})");

            usort($missingImages, fn ($a, $b) => $a->sort_order <=> $b->sort_order);

            foreach ($missingImages as $image) {
                $sourceUrl = $this->resolveSourceUrl($image, $yacht);

                if (!$sourceUrl) {
                    $this->warn("    Image #{$image->id} sort_order={$image->sort_order}: could not construct source URL.");
                    $failed++;
                    continue;
                }

                $ok = $this->redownloadToPath($sourceUrl, $image->url);

                if ($ok) {
                    $this->line("    ✓ Image #{$image->id} restored from {$sourceUrl}");
                    $downloadedOk++;
                } else {
                    $this->warn("    ✗ Image #{$image->id} failed ({$sourceUrl})");
                    $failed++;
                }
            }

            $processed++;
        }

        $this->info("Done. Restored: {$downloadedOk}, Failed: {$failed}.");
        return self::SUCCESS;
    }

    /**
     * Resolve the original source URL for a missing image.
     *
     * For schepenkring sold archive, images are served directly from a predictable
     * vibp plugin path: /wp-content/plugins/vibp/assets/verkochte_boten/images/{id}_{index}.jpg
     * No page scraping needed — source_identifier + sort_order are sufficient.
     *
     * Falls back to scraping the listing page for other sources.
     */
    private function resolveSourceUrl(YachtImage $image, ?object $yacht): ?string
    {
        $sourceId = $yacht?->source_identifier;
        $source   = $yacht?->source;

        // Direct URL construction for Schepenkring sold archive (vibp plugin CDN)
        if ($sourceId && $source === 'schepenkring_sold_archive') {
            return sprintf(
                'https://www.schepenkring.nl/wp-content/plugins/vibp/assets/verkochte_boten/images/%s_%d.jpg',
                $sourceId,
                $image->sort_order
            );
        }

        // Fallback: scrape the listing page for other sources
        if (!$yacht?->external_url) {
            return null;
        }

        $pageImages = $this->scrapeImageUrls((string) $yacht->external_url);
        return $pageImages[$image->sort_order] ?? null;
    }

    // ── Scrape image URLs from a listing page (fallback for non-vibp sources) ──

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

            $urls = $crawler->filter(
                'img[src*="/vibp/assets/verkochte_boten/"], img[src*="/previews/"], img[src*="/uploads/"]'
            )->each(fn (Crawler $node) => $this->normalizeUrl($node->attr('src'), $pageUrl));

            return collect($urls)
                ->filter()
                ->unique()
                ->reject(fn (string $url) => $this->isTemplateImage($url))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning("[RedownloadMissingImages] Failed scraping {$pageUrl}: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Returns true for WordPress site-asset images that are never actual boat photos.
     * These appear on every page regardless of content (logos, icons, footer images).
     */
    private function isTemplateImage(string $url): bool
    {
        // Reject dated WordPress media uploads (e.g. /wp-content/uploads/2024/05/).
        // Real boat images don't use this path — they come from plugin or preview directories.
        if (preg_match('#/wp-content/uploads/\d{4}/\d{2}/#', $url)) {
            return true;
        }

        // Reject by filename keywords that indicate site branding / UI assets.
        $basename = strtolower(basename(parse_url($url, PHP_URL_PATH) ?? ''));
        foreach (['logo', 'icon', 'footer', 'hiswa', 'nbms', 'gdpr', 'banner', 'badge'] as $keyword) {
            if (str_contains($basename, $keyword)) {
                return true;
            }
        }

        return false;
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
