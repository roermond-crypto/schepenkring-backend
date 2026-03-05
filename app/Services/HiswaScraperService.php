<?php

namespace App\Services;

use App\Models\Harbor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HiswaScraperService
{
    private string $baseUrl;
    private int $perPage = 20;

    public function __construct()
    {
        $this->baseUrl = config('services.hiswa.base_url', 'https://www.hiswa.nl');
    }

    /**
     * Scrape HISWA jachthavens listing pages.
     *
     * @param int|null $limit  Max harbors to scrape (null = all)
     * @return array           Summary of scrape results
     */
    public function scrape(?int $limit = null, bool $dryRun = false): array
    {
        $results = ['created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0];
        $page = 1;
        $totalScraped = 0;

        Log::info('[HISWA Scraper] Starting scrape...');

        while (true) {
            try {
                $listingUrl = $this->buildListingUrl($page);
                $response = Http::timeout(30)->get($listingUrl);

                if (!$response->successful()) {
                    Log::warning("[HISWA Scraper] Failed to fetch page {$page}: HTTP {$response->status()}");
                    break;
                }

                $html = $response->body();
                $entries = $this->parseListingPage($html);

                if (empty($entries)) {
                    Log::info("[HISWA Scraper] No more entries on page {$page}, stopping.");
                    break;
                }

                foreach ($entries as $entry) {
                    if ($limit !== null && $totalScraped >= $limit) {
                        Log::info("[HISWA Scraper] Limit of {$limit} reached.");
                        return $results;
                    }

                    try {
                        // Fetch detail page for richer data
                        $detail = $this->scrapeDetailPage($entry['detail_url'] ?? null);
                        $merged = array_merge($entry, $detail);

                        $result = $this->upsertHarbor($merged, $dryRun);
                        $results[$result]++;
                        $totalScraped++;
                    } catch (\Exception $e) {
                        Log::error("[HISWA Scraper] Error processing entry: {$e->getMessage()}", [
                            'entry' => $entry['name'] ?? 'unknown',
                        ]);
                        $results['errors']++;
                    }
                }

                $page++;
                // Polite delay between pages
                usleep(500_000); // 500ms
            } catch (\Exception $e) {
                Log::error("[HISWA Scraper] Fatal error on page {$page}: {$e->getMessage()}");
                $results['errors']++;
                break;
            }
        }

        Log::info('[HISWA Scraper] Scrape complete.', $results);
        return $results;
    }

    /**
     * Build the URL for a HISWA listing page.
     * Uses the "Jachthavens" category filter: f[categorieen][707][]=875
     */
    private function buildListingUrl(int $page): string
    {
        $base = "{$this->baseUrl}/bedrijven/hiswa-erkende-bedrijven";
        $params = ['f[categorieen][707][]' => '875'];
        if ($page > 1) {
            $params['page'] = $page;
        }
        return $base . '?' . http_build_query($params);
    }

    /**
     * Parse HTML listing page and extract harbor entries.
     *
     * @return array  Array of ['name','address','detail_url','hiswa_company_id',...]
     */
    private function parseListingPage(string $html): array
    {
        $entries = [];

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        // Business cards: <div class="cell member">
        $cards = $xpath->query("//div[contains(@class, 'cell') and contains(@class, 'member')]");

        if ($cards === false || $cards->length === 0) {
            // Fallback: try .member-grid children
            $cards = $xpath->query("//div[contains(@class, 'member-grid')]//div[contains(@class, 'cell')]");
        }

        foreach ($cards as $card) {
            try {
                $entry = $this->extractCardData($card, $xpath);
                if (!empty($entry['name'])) {
                    $entries[] = $entry;
                }
            } catch (\Exception $e) {
                Log::debug("[HISWA Scraper] Could not parse card: {$e->getMessage()}");
            }
        }

        return $entries;
    }

    /**
     * Extract data from a single listing card.
     */
    private function extractCardData(\DOMElement $card, \DOMXPath $xpath): array
    {
        $data = [
            'name'             => '',
            'street_address'   => '',
            'city'             => '',
            'postal_code'      => '',
            'phone'            => '',
            'email'            => '',
            'website'          => '',
            'detail_url'       => '',
            'hiswa_company_id' => '',
        ];

        // Company name: first <a> link that is NOT an image link and NOT the "Meer informatie" button
        $nameNodes = $xpath->query(".//a[not(contains(@class, 'member-image')) and not(contains(@class, 'button'))]", $card);
        if ($nameNodes->length > 0) {
            $nameNode = $nameNodes->item(0);
            $data['name'] = trim($nameNode->textContent);
        }

        // Detail URL: "Meer informatie" button
        $detailNode = $xpath->query(".//a[contains(@class, 'button')]", $card)->item(0);
        if ($detailNode) {
            $href = $detailNode->getAttribute('href');
            $data['detail_url'] = str_starts_with($href, 'http') ? $href : $this->baseUrl . $href;

            // Extract company ID from the URL slug
            // Pattern: /bedrijven/hiswa-erkende-bedrijven/company-name-123
            if (preg_match('/hiswa-erkende-bedrijven\/([^\/\?]+)/', $href, $m)) {
                $data['hiswa_company_id'] = Str::slug($m[1]);
            }
        }

        // Address: text nodes directly in the card (after the name link, not inside other elements)
        // The card structure shows: Name, Street, PostalCode City
        $textNodes = $xpath->query(".//text()[normalize-space()]", $card);
        $addressLines = [];
        $skipTexts = ['meer informatie', 'hiswa', $data['name']];

        foreach ($textNodes as $textNode) {
            $text = trim($textNode->textContent);
            if (empty($text)) continue;

            // Skip the name, button text, and logo text
            $lowerText = mb_strtolower($text);
            $skip = false;
            foreach ($skipTexts as $s) {
                if (mb_strtolower($s) === $lowerText || str_contains($lowerText, 'meer informatie')) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            // Check if this looks like an address line
            if (preg_match('/\d{4}\s?[A-Z]{2}/', $text) || preg_match('/^[A-Z].*\d/', $text) || preg_match('/^\d/', $text)) {
                $addressLines[] = $text;
            }
        }

        if (!empty($addressLines)) {
            // First address line is usually the street
            $data['street_address'] = $addressLines[0] ?? '';

            // Second line contains postal code + city
            if (isset($addressLines[1])) {
                $this->parseAddressText($addressLines[1], $data);
            }
        }

        // Phone (if present on card)
        $phoneNode = $xpath->query(".//a[contains(@href, 'tel:')]", $card)->item(0);
        if ($phoneNode) {
            $data['phone'] = trim($phoneNode->textContent);
        }

        return $data;
    }

    /**
     * Scrape a detail page for additional data.
     */
    private function scrapeDetailPage(?string $url): array
    {
        if (!$url) return [];

        try {
            $response = Http::timeout(30)->get($url);
            if (!$response->successful()) return [];

            $html = $response->body();
            $dom = new \DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new \DOMXPath($dom);

            $detail = [];

            // Description: look for main content area
            $descNode = $xpath->query("//*[contains(@class, 'content-block')] | //*[contains(@class, 'description')] | //*[contains(@class, 'tekst')]")->item(0);
            if ($descNode) {
                $descText = trim($descNode->textContent);
                if (strlen($descText) > 20) {
                    $detail['description'] = $descText;
                }
            }

            // Email: a[href^="mailto:"]
            $emailNode = $xpath->query("//a[starts-with(@href, 'mailto:')]")->item(0);
            if ($emailNode) {
                $detail['email'] = str_replace('mailto:', '', $emailNode->getAttribute('href'));
            }

            // Phone: a[href^="tel:"]
            $phoneNode = $xpath->query("//a[starts-with(@href, 'tel:')]")->item(0);
            if ($phoneNode) {
                $detail['phone'] = trim($phoneNode->textContent);
            }

            // Website: a.underline[href^="http"] that is NOT hiswa.nl
            $linkNodes = $xpath->query("//a[contains(@class, 'underline') and starts-with(@href, 'http')]");
            foreach ($linkNodes as $linkNode) {
                $href = $linkNode->getAttribute('href');
                if (!str_contains($href, 'hiswa.nl') && !str_contains($href, 'mailto:')) {
                    $detail['website'] = $href;
                    break;
                }
            }

            // Facilities / categories: look for tag-like elements
            $facilityNodes = $xpath->query("//*[contains(@class, 'tag')] | //*[contains(@class, 'label')] | //*[contains(@class, 'categorie')]");
            if ($facilityNodes->length > 0) {
                $facilities = [];
                foreach ($facilityNodes as $f) {
                    $text = trim($f->textContent);
                    if ($text && strlen($text) < 60) {
                        $facilities[] = $text;
                    }
                }
                if (!empty($facilities)) {
                    $detail['facilities'] = array_values(array_unique($facilities));
                }
            }

            // Full address from detail page sidebar
            $addrLines = $xpath->query("//*[contains(@class, 'sidebar')]//*[contains(@class, 'address')] | //*[contains(@class, 'adres')]");
            if ($addrLines->length > 0) {
                $fullAddr = trim($addrLines->item(0)->textContent);
                if ($fullAddr) {
                    $this->parseAddressText($fullAddr, $detail);
                }
            }

            // Polite delay
            usleep(300_000); // 300ms

            return $detail;
        } catch (\Exception $e) {
            Log::warning("[HISWA Scraper] Detail page error for {$url}: {$e->getMessage()}");
            return [];
        }
    }

    private function parseAddressText(string $text, array &$data): void
    {
        // Dutch postal code pattern: 1234 AB or 1234AB
        if (preg_match('/(\d{4}\s?[A-Z]{2})/', $text, $m)) {
            $data['postal_code'] = $m[1];

            // City: everything after the postal code
            $afterPostal = trim(preg_replace('/.*\d{4}\s?[A-Z]{2}\s*/', '', $text));
            if ($afterPostal) {
                $data['city'] = $afterPostal;
            }
        }

        // Split by comma or newline for multi-part addresses
        $parts = preg_split('/[,\n\r]+/', $text);
        if (count($parts) >= 2) {
            $firstPart = trim($parts[0]);
            // If the first part doesn't look like a postal code, it's the street
            if (!preg_match('/^\d{4}\s?[A-Z]{2}/', $firstPart)) {
                $data['street_address'] = $firstPart;
            }
        } elseif (count($parts) === 1 && empty($data['city'])) {
            // Single line — try to extract city after postal code
            $city = preg_replace('/\d{4}\s?[A-Z]{2}\s*/', '', $text);
            $city = trim($city);
            if ($city) {
                $data['city'] = $city;
            }
        }
    }

    /**
     * Upsert a single harbor record.
     *
     * @return string  'created' | 'updated' | 'skipped'
     */
    private function upsertHarbor(array $data, bool $dryRun = false): string
    {
        $companyId = $data['hiswa_company_id'] ?? null;

        if (!$companyId && empty($data['name'])) {
            return 'skipped';
        }

        $harbor = $companyId
            ? Harbor::where('hiswa_company_id', $companyId)->first()
            : null;

        $now = now();

        $fields = [
            'name'           => $data['name'] ?? '',
            'description'    => $data['description'] ?? null,
            'street_address' => $data['street_address'] ?? null,
            'postal_code'    => $data['postal_code'] ?? null,
            'city'           => $data['city'] ?? null,
            'email'          => $data['email'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'website'        => $data['website'] ?? null,
            'facilities'     => $data['facilities'] ?? null,
            'source_url'     => $data['detail_url'] ?? null,
            'last_seen_at'   => $now,
        ];

        if ($harbor) {
            if ($dryRun) {
                return 'updated';
            }

            $addressChanged = $this->addressChanged($harbor, $fields);
            $harbor->update($fields);

            if ($addressChanged) {
                // Address changed: force a fresh geocode and place details on next enrichment run.
                $harbor->update([
                    'gmaps_place_id' => null,
                    'gmaps_formatted_address' => null,
                    'lat' => null,
                    'lng' => null,
                    'address_components' => null,
                    'geocode_confidence' => null,
                    'maps_url' => null,
                    'geocode_query_hash' => null,
                    'last_geocode_at' => null,
                    'last_place_details_fetch_at' => null,
                    'last_place_photos_fetch_at' => null,
                ]);
            }

            return 'updated';
        }

        // New harbor
        $fields['hiswa_company_id'] = $companyId;
        $fields['slug'] = Harbor::generateSlug($data['name'], $data['city'] ?? null);
        $fields['first_seen_at'] = $now;

        if ($dryRun) {
            return 'created';
        }

        Harbor::create($fields);
        return 'created';
    }

    private function addressChanged(Harbor $harbor, array $newFields): bool
    {
        return ($harbor->street_address ?? null) !== ($newFields['street_address'] ?? null)
            || ($harbor->postal_code ?? null) !== ($newFields['postal_code'] ?? null)
            || ($harbor->city ?? null) !== ($newFields['city'] ?? null)
            || ($harbor->country ?? 'NL') !== ($newFields['country'] ?? 'NL');
    }
}
