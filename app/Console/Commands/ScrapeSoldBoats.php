<?php

namespace App\Console\Commands;

use App\Models\Yacht;
use App\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class ScrapeSoldBoats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scrape-sold-boats {--limit= : Limit the number of boats to scrape} {--page=1 : Start page} {--max-pages= : Maximum number of pages to crawl}';

    /**
     * Backward-compatible alias.
     *
     * @var array<int, string>
     */
    protected $aliases = [
        'app:scrape-schepenkring-sold',
    ];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape sold boats archive from schepenkring.nl and store in the yachts table';

    private array $locationMap = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting sold boats scraper...');
        
        $startPage = (int) $this->option('page');
        $maxPages = $this->option('max-pages') ? (int) $this->option('max-pages') : 1000;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        
        $this->loadLocations();
        
        $boatsScraped = 0;
        $currentPage = $startPage;

        while ($currentPage <= $maxPages) {
            $url = "https://www.schepenkring.nl/verkochte-boten/?page-view={$currentPage}";
            $this->info("Crawling page {$currentPage}: {$url}");

            try {
                $response = Http::timeout(20)->retry(2, 300)->get($url);
                if ($response->failed()) {
                    $this->error("Failed to fetch page {$currentPage}");
                    break;
                }

                $crawler = new Crawler($response->body());
                $boatLinks = $crawler->filter('a.botenloop')->each(function (Crawler $node) {
                    return $node->attr('href');
                });
                $boatLinks = collect($boatLinks)
                    ->map(fn($href) => $this->normalizeExternalUrl($href, $url))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (empty($boatLinks)) {
                    $this->info("No more boats found on page {$currentPage}.");
                    break;
                }

                foreach ($boatLinks as $boatUrl) {
                    if ($limit && $boatsScraped >= $limit) {
                        $this->info("Reached limit of {$limit} boats.");
                        return 0;
                    }

                    $this->scrapeBoat($boatUrl);
                    $boatsScraped++;
                }

                $currentPage++;
                
                // Be nice to the server
                sleep(1);

            } catch (\Exception $e) {
                $this->error("Error on page {$currentPage}: " . $e->getMessage());
                break;
            }
        }

        $this->info("Scraping completed. Total boats processed: {$boatsScraped}");
        return 0;
    }

    private function loadLocations()
    {
        $locations = Location::all();
        foreach ($locations as $loc) {
            // Map common city names or aliases if needed
            $this->locationMap[strtolower($loc->name)] = $loc->id;
            // Also try to extract from city if name is different
            if ($loc->city) {
                $this->locationMap[strtolower($loc->city)] = $loc->id;
            }
        }
    }

    private function scrapeBoat(string $url)
    {
        $this->comment("Scraping boat: {$url}");

        // Extract ID from URL: .../aanbod-boten/5000066/valk-super-falcon-45-gs/
        preg_match('/\/(\d+)\/([^\/]+)\//', $url, $matches);
        $sourceIdentifier = $matches[1] ?? Str::slug($url);
        
        $existingYacht = Yacht::where('source_identifier', $sourceIdentifier)->first();
        if ($existingYacht) {
            $this->warn("Boat already exists: {$sourceIdentifier}. Updating record and images.");
        }

        try {
            $response = Http::timeout(20)->retry(2, 300)->get($url);
            if ($response->failed()) {
                $this->error("Failed to fetch boat page: {$url}");
                return;
            }

            $crawler = new Crawler($response->body());
            
            $title = $crawler->filter('h1.vibp_topbar_title.notranslate')->count() > 0 
                ? trim($crawler->filter('h1.vibp_topbar_title.notranslate')->text()) 
                : 'Unknown Boat';

            $specs = [];
            $crawler->filter('.vibp_spec_row')->each(function (Crawler $row) use (&$specs) {
                $keyNode = $row->filter('.vibp_spec_name');
                $valNode = $row->filter('.vibp_spec_value');
                if ($keyNode->count() > 0 && $valNode->count() > 0) {
                    $specs[trim($keyNode->text(), " :\t\n\r\0\x0B")] = trim($valNode->text());
                }
            });

            // Clean description
            $descriptionHtml = '';
            $crawler->filter('.vibp_spec_row')->each(function (Crawler $row) use (&$descriptionHtml) {
                $keyNode = $row->filter('.vibp_spec_name');
                if ($keyNode->count() > 0 && str_contains(strtolower($keyNode->text()), 'opmerkingen')) {
                    $descriptionHtml = $row->filter('.vibp_spec_value')->html();
                }
            });
            
            // If Opmerkingen not found, try common text areas
            if (!$descriptionHtml && $crawler->filter('.vibp_text_block_content')->count() > 0) {
                $descriptionHtml = $crawler->filter('.vibp_text_block_content')->first()->html();
            }

            // Extract images
            $images = $crawler->filter('a[id^="click_"]')->each(function (Crawler $node) {
                return $node->attr('href');
            });
            $images = collect($images)
                ->map(fn($href) => $this->normalizeExternalUrl($href, $url))
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Map location
            $locationName = $specs['Ligplaats'] ?? $specs['Location'] ?? null;
            $locationId = null;
            if ($locationName) {
                // Try exact match or contains
                foreach ($this->locationMap as $name => $id) {
                    if (str_contains(strtolower($locationName), $name)) {
                        $locationId = $id;
                        break;
                    }
                }
            }

            // Create Yacht record
            $yacht = $existingYacht ?: new Yacht();
            $yacht->boat_name = $title;
            $yacht->status = 'sold';
            $yacht->source = 'schepenkring_sold_archive';
            $yacht->external_url = $url;
            $yacht->source_identifier = $sourceIdentifier;
            $yacht->short_description_nl = strip_tags($descriptionHtml);
            
            // Basic spec mapping that we can infer
            if (isset($specs['Bouwjaar'])) $yacht->year = (int) $specs['Bouwjaar'];
            if (isset($specs['Merk'])) $yacht->manufacturer = $specs['Merk'];
            if (isset($specs['Type'])) $yacht->model = $specs['Type'];
            if (isset($specs['Categorie'])) $yacht->boat_category = $specs['Categorie'];
            if ($locationName) $yacht->vessel_lying = $locationName;

            // Location ID if found
            // Since Yacht model might have location_id or similar (need to check columns)
            // Looking at Yacht.php fillable, it doesn't show location_id but location_city, etc.
            // I'll store location_city if I can't find a direct ID mapping for now.
            if ($locationName) $yacht->location_city = $locationName;
            
            // Save initial record
            $yacht->save();

            if ($existingYacht) {
                $this->clearYachtImages($yacht);
            }

            // Download and store images locally when possible.
            $mainImagePath = null;
            foreach ($images as $index => $imageUrl) {
                $downloadedPath = $this->downloadImage(
                    $imageUrl,
                    "yachts/imported/schepenkring/{$sourceIdentifier}",
                    "sold_{$sourceIdentifier}_{$index}"
                );

                $storedPath = $downloadedPath ?: $imageUrl;
                if ($index === 0 && $downloadedPath) {
                    $mainImagePath = $downloadedPath;
                }

                $yacht->images()->create([
                    'url' => $storedPath,
                    'category' => 'General',
                    'part_name' => 'General',
                    'status' => 'approved',
                    'original_name' => basename((string) parse_url($imageUrl, PHP_URL_PATH)),
                    'sort_order' => $index,
                ]);
            }

            if ($mainImagePath) {
                $yacht->main_image = $mainImagePath;
                $yacht->save();
            } elseif (!empty($images)) {
                $yacht->main_image = $images[0];
                $yacht->save();
            }

            // Sub-tables: engine, dimensions etc.
            $subData = [];
            if (isset($specs['Lengte'])) {
                // Parse "14.00 m" -> 14.00
                $val = floatval(str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $specs['Lengte'])));
                $subData['loa'] = $val;
            }
            if (isset($specs['Breedte'])) {
                $val = floatval(str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $specs['Breedte'])));
                $subData['beam'] = $val;
            }
            if (isset($specs['Diepgang'])) {
                $val = floatval(str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $specs['Diepgang'])));
                $subData['draft'] = $val;
            }
            
            // Engine details
            $engineData = [];
            if (isset($specs['Motor merk'])) $engineData['engine_manufacturer'] = $specs['Motor merk'];
            if (isset($specs['Aantal motoren'])) $engineData['engine_quantity'] = (int) $specs['Aantal motoren'];
            if (isset($specs['Brandstof'])) $engineData['fuel'] = $specs['Brandstof'];
            
            $yacht->saveSubTables(array_merge($subData, $engineData));

            $this->info("Successfully saved boat: {$title}");

        } catch (\Exception $e) {
            $this->error("Error scraping boat {$url}: " . $e->getMessage());
            Log::error("Scraper error for {$url}: " . $e->getMessage());
        }
    }

    private function clearYachtImages(Yacht $yacht): void
    {
        $yacht->loadMissing('images');

        foreach ($yacht->images as $image) {
            $paths = array_filter([
                $image->url,
                $image->original_temp_url,
                $image->optimized_master_url,
                $image->thumb_url,
                $image->original_kept_url,
            ]);

            foreach ($paths as $path) {
                if (!$this->isAbsoluteUrl($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        }

        $yacht->images()->delete();
    }

    private function normalizeExternalUrl(?string $url, string $baseUrl): ?string
    {
        $url = trim((string) $url);
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

    private function downloadImage(string $url, string $directory, string $filenamePrefix): ?string
    {
        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; NauticSecureBot/1.0)',
                ])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $contents = $response->body();
            if (!$contents) {
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            $ext = $this->resolveImageExtension($url, $contentType);
            $filename = "{$filenamePrefix}_" . time() . '_' . Str::random(4) . ".{$ext}";
            $path = "{$directory}/{$filename}";

            Storage::disk('public')->put($path, $contents);
            return $path;
        } catch (\Throwable $e) {
            Log::warning("Failed downloading image {$url}: {$e->getMessage()}");
            return null;
        }
    }

    private function resolveImageExtension(string $url, string $contentType): string
    {
        if (str_contains($contentType, 'image/png')) {
            return 'png';
        }
        if (str_contains($contentType, 'image/webp')) {
            return 'webp';
        }
        if (str_contains($contentType, 'image/gif')) {
            return 'gif';
        }
        if (str_contains($contentType, 'image/jpeg') || str_contains($contentType, 'image/jpg')) {
            return 'jpg';
        }

        $fromUrl = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($fromUrl, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $fromUrl === 'jpeg' ? 'jpg' : $fromUrl;
        }

        return 'jpg';
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return preg_match('/^https?:\/\//i', $value) === 1;
    }
}
