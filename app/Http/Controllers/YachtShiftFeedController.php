<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class YachtShiftFeedController extends Controller
{
    /**
     * Fetch and parse the YachtShift XML feed.
     * Returns a JSON array of boat objects with images, specs, and metadata.
     *
     * GET /api/yachtshift/feed?url={feed_url}
     */
    public function fetch(Request $request)
    {
        $feedUrl = $request->input('url');

        if (!$feedUrl) {
            return response()->json(['error' => 'Feed URL is required'], 400);
        }

        // Cache for 30 minutes to avoid hammering the feed
        $cacheKey = 'yachtshift_feed_' . md5($feedUrl);
        $boats = Cache::remember($cacheKey, 1800, function () use ($feedUrl) {
            return $this->parseFeed($feedUrl);
        });

        if ($boats === null) {
            return response()->json(['error' => 'Failed to fetch or parse feed'], 500);
        }

        return response()->json([
            'success' => true,
            'total'   => count($boats),
            'boats'   => $boats,
        ]);
    }

    /**
     * Force refresh the feed cache.
     * POST /api/yachtshift/feed/refresh
     */
    public function refresh(Request $request)
    {
        $feedUrl = $request->input('url');

        if (!$feedUrl) {
            return response()->json(['error' => 'Feed URL is required'], 400);
        }

        $cacheKey = 'yachtshift_feed_' . md5($feedUrl);
        Cache::forget($cacheKey);

        $boats = $this->parseFeed($feedUrl);

        if ($boats === null) {
            return response()->json(['error' => 'Failed to fetch or parse feed'], 500);
        }

        Cache::put($cacheKey, $boats, 1800);

        return response()->json([
            'success' => true,
            'total'   => count($boats),
            'boats'   => $boats,
        ]);
    }

    /**
     * Parse the OpenMarine XML feed into a structured array.
     */
    private function parseFeed(string $feedUrl): ?array
    {
        try {
            $response = Http::timeout(30)->get($feedUrl);

            if (!$response->successful()) {
                Log::error('[YachtShift] Feed fetch failed: ' . $response->status());
                return null;
            }

            $xmlString = $response->body();
            $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

            if (!$xml) {
                Log::error('[YachtShift] Failed to parse XML');
                return null;
            }

            $boats = [];

            // Navigate: open_marine > broker > adverts > advert
            foreach ($xml->broker->adverts->advert ?? [] as $advert) {
                $boat = $this->parseAdvert($advert);
                if ($boat) {
                    $boats[] = $boat;
                }
            }

            Log::info('[YachtShift] Parsed ' . count($boats) . ' boats from feed');

            return $boats;

        } catch (\Exception $e) {
            Log::error('[YachtShift] Feed parsing exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse a single <advert> element into a boat array.
     */
    private function parseAdvert(\SimpleXMLElement $advert): ?array
    {
        $attrs = $advert->attributes();
        $ref   = (string) ($attrs['ref'] ?? '');
        $status = (string) ($attrs['status'] ?? '');

        // Extract images
        $images = [];
        $primaryImage = null;
        foreach ($advert->advert_media->media ?? [] as $media) {
            $url = (string) $media;
            $isPrimary = ((string) $media->attributes()['primary'] ?? '0') === '1';
            $type = (string) ($media->attributes()['type'] ?? '');

            if (strpos($type, 'image') !== false && !empty($url)) {
                $images[] = $url;
                if ($isPrimary || !$primaryImage) {
                    $primaryImage = $url;
                }
            }
        }

        // Extract advert features
        $features = $advert->advert_features;
        $manufacturer = (string) ($features->manufacturer ?? '');
        $model        = (string) ($features->model ?? '');
        $boatType     = (string) ($features->boat_type ?? '');
        $boatCategory = (string) ($features->boat_category ?? '');
        $newOrUsed    = (string) ($features->new_or_used ?? '');

        // Price
        $priceEl = $features->asking_price;
        $price   = $priceEl ? (float) (string) $priceEl : null;
        $currency = $priceEl ? (string) ($priceEl->attributes()['currency'] ?? 'EUR') : 'EUR';

        // Location
        $vesselLying = (string) ($features->vessel_lying ?? '');
        $country     = $features->vessel_lying ? (string) ($features->vessel_lying->attributes()['country'] ?? '') : '';

        // External URL
        $externalUrl = '';
        foreach ($features->other->item ?? [] as $item) {
            if ((string) $item->attributes()['name'] === 'external_url') {
                $externalUrl = (string) $item;
            }
        }

        // Boat features
        $bf = $advert->boat_features;
        $boatName = $this->getFeature($bf, 'boat_name');
        $year     = $this->getFeature($bf, 'year', 'build');

        // Dimensions (convert from cm to meters)
        $loa   = $this->getDimension($bf, 'loa');
        $beam  = $this->getDimension($bf, 'beam');
        $draft = $this->getDimension($bf, 'draft');

        // Build details
        $hullColour       = $this->getFeature($bf, 'hull_colour', 'build');
        $hullConstruction = $this->getFeature($bf, 'hull_construction', 'build');

        // Engine
        $engineManufacturer = $this->getFeature($bf, 'engine_manufacturer', 'engine');
        $horsePower = $this->getFeature($bf, 'horse_power', 'engine');
        $fuel = $this->getFeature($bf, 'fuel', 'engine');

        // Accommodation
        $cabins = $this->getFeature($bf, 'cabins', 'accommodation');
        $berths = $this->getFeature($bf, 'berths', 'accommodation');

        return [
            'id'                  => $ref,
            'status'              => $status,
            'manufacturer'        => $manufacturer,
            'model'               => $model,
            'boat_name'           => $boatName,
            'boat_type'           => $boatType,
            'boat_category'       => $boatCategory,
            'new_or_used'         => $newOrUsed,
            'year'                => $year,
            'price'               => $price,
            'currency'            => $currency,
            'location'            => $vesselLying,
            'country'             => $country,
            'external_url'        => $externalUrl,
            'loa'                 => $loa,
            'beam'                => $beam,
            'draft'               => $draft,
            'hull_colour'         => $hullColour,
            'hull_construction'   => $hullConstruction,
            'engine_manufacturer' => $engineManufacturer,
            'horse_power'         => $horsePower,
            'fuel'                => $fuel,
            'cabins'              => $cabins,
            'berths'              => $berths,
            'primary_image'       => $primaryImage,
            'images'              => $images,
            'image_count'         => count($images),
        ];
    }

    /**
     * Helper: Get a feature value from the boat_features XML.
     */
    private function getFeature(?\SimpleXMLElement $bf, string $name, ?string $section = null): ?string
    {
        if (!$bf) return null;

        $parent = $section ? ($bf->$section ?? $bf) : $bf;

        foreach ($parent->item ?? [] as $item) {
            if ((string) $item->attributes()['name'] === $name) {
                $val = trim((string) $item);
                return $val !== '' ? $val : null;
            }
        }

        return null;
    }

    /**
     * Helper: Get dimension value and convert from cm to meters.
     */
    private function getDimension(?\SimpleXMLElement $bf, string $name): ?string
    {
        if (!$bf || !$bf->dimensions) return null;

        foreach ($bf->dimensions->item ?? [] as $item) {
            if ((string) $item->attributes()['name'] === $name) {
                $val = trim((string) $item);
                if ($val !== '' && is_numeric($val)) {
                    $unit = (string) ($item->attributes()['unit'] ?? 'centimetres');
                    if ($unit === 'centimetres') {
                        return round((float) $val / 100, 2) . 'm';
                    }
                    return $val . 'm';
                }
            }
        }

        return null;
    }

    /**
     * List all boats from the database (yachtshift_raw_boats).
     * Supports pagination, search, and type filtering.
     *
     * GET /api/yachtshift/boats?page=1&per_page=50&search=bavaria&type=motor
     */
    public function listFromDatabase(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 50), 200);
        $search  = $request->input('search', '');
        $type    = $request->input('type', '');

        $query = \Illuminate\Support\Facades\DB::table('yachtshift_raw_boats')
            ->orderBy('id', 'desc');

        // Get all rows, decode payload, then filter in PHP since payload is JSON
        // For large datasets this is fine since raw_payload searching needs JSON decode
        $allRows = $query->get();

        $boats = $allRows->map(function ($row) {
        $payload = json_decode($row->raw_payload, true);
        if (!$payload) return null;

        $images = $payload['images'] ?? [];
        // Filter out non-image URLs (youtube, etc)
        $images = array_values(array_filter($images, function($url) {
            return !empty($url) && !str_contains($url, 'youtube.com') && !str_contains($url, 'youtu.be');
        }));
        $primaryImage = !empty($images) ? $images[0] : null;

        $length = $payload['length'] ?? null;
        $beam = $payload['beam'] ?? null;
        $draft = $payload['draft'] ?? null;

        return [
            'id'                  => $payload['id'] ?? $row->yachtshift_id,
            'status'              => $payload['status'] ?? 'imported',
            'manufacturer'        => $payload['make'] ?? '',
            'model'               => $payload['model'] ?? '',
            'boat_name'           => $payload['boat_name'] ?? '',
            'boat_type'           => $payload['type'] ?? '',
            'boat_category'       => $payload['boat_category'] ?? $payload['type'] ?? '',
            'new_or_used'         => $payload['new_or_used'] ?? '',
            'year'                => isset($payload['year']) && $payload['year'] ? (string) $payload['year'] : null,
            'price'               => isset($payload['price_eur']) ? (float) $payload['price_eur'] : null,
            'currency'            => $payload['currency'] ?? 'EUR',
            'location'            => $payload['location'] ?? '',
            'country'             => $payload['country'] ?? '',
            'external_url'        => $payload['external_url'] ?? '',
            'loa'                 => $length ? $length . 'm' : null,
            'beam'                => $beam ? $beam . 'm' : null,
            'draft'               => $draft ? $draft . 'm' : null,
            'hull_colour'         => $payload['hull_colour'] ?? null,
            'hull_construction'   => $payload['hull_construction'] ?? null,
            'engine_manufacturer' => $payload['engine_make'] ?? null,
            'horse_power'         => $payload['horse_power'] ?? null,
            'fuel'                => $payload['fuel'] ?? null,
            'cabins'              => $payload['cabins'] ?? null,
            'berths'              => $payload['berths'] ?? null,
            'description'         => $payload['description'] ?? null,
            'primary_image'       => $primaryImage,
            'images'              => $images,
            'image_count'         => count($images),
            'db_id'               => $row->id,
            'normalize_status'    => $row->status,
        ];
    })->filter();

        // Apply search filter
        if ($search) {
            $searchLower = strtolower($search);
            $boats = $boats->filter(function ($boat) use ($searchLower) {
                $haystack = strtolower(
                    ($boat['manufacturer'] ?? '') . ' ' .
                    ($boat['model'] ?? '') . ' ' .
                    ($boat['boat_name'] ?? '') . ' ' .
                    ($boat['boat_type'] ?? '')
                );
                return str_contains($haystack, $searchLower);
            });
        }

        // Apply type filter
        if ($type && $type !== 'all') {
            $typeLower = strtolower($type);
            $boats = $boats->filter(function ($boat) use ($typeLower) {
                return strtolower($boat['boat_type'] ?? '') === $typeLower;
            });
        }

        $total = $boats->count();
        $page  = max(1, (int) $request->input('page', 1));
        $paged = $boats->values()->forPage($page, $perPage)->values();

        // Get unique types for filter dropdown
        $types = $boats->pluck('boat_type')->filter()->unique()->values();
        // Get unique statuses
        $statuses = $boats->pluck('normalize_status')->filter()->unique()->values();

        return response()->json([
            'success'      => true,
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'total_pages'  => (int) ceil($total / $perPage),
            'boats'        => $paged,
            'boat_types'   => $types,
            'boat_statuses' => $statuses,
        ]);
    }
}
