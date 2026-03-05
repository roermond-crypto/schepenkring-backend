<?php

namespace App\Http\Controllers;

use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdvertisingController extends Controller
{
    /**
     * Available advertising platforms with metadata.
     */
    private const PLATFORMS = [
        [
            'slug' => 'schepen_kring',
            'name' => 'Schepen Kring',
            'logo' => '/logos/schepen-kring.svg',
            'status' => 'active',
            'description' => 'Your own fleet website',
            'always_on' => true,
        ],
        [
            'slug' => 'google_merchant',
            'name' => 'Google Merchant',
            'logo' => '/logos/google-merchant.svg',
            'status' => 'active',
            'description' => 'Google Shopping product feed',
            'always_on' => false,
        ],
        [
            'slug' => 'yachtshift',
            'name' => 'Yachtshift',
            'logo' => '/logos/yachtshift.svg',
            'status' => 'active',
            'description' => 'OpenMarine XML syndication',
            'always_on' => false,
        ],
        [
            'slug' => 'boat24',
            'name' => 'Boat24',
            'logo' => '/logos/boat24.svg',
            'status' => 'coming_soon',
            'description' => 'European boat marketplace',
            'always_on' => false,
        ],
        [
            'slug' => 'yachtworld',
            'name' => 'YachtWorld',
            'logo' => '/logos/yachtworld.svg',
            'status' => 'coming_soon',
            'description' => 'Global yacht marketplace',
            'always_on' => false,
        ],
        [
            'slug' => 'yachtfocus',
            'name' => 'YachtFocus',
            'logo' => '/logos/yachtfocus.svg',
            'status' => 'coming_soon',
            'description' => 'Dutch yacht search engine',
            'always_on' => false,
        ],
        [
            'slug' => 'boats_com',
            'name' => 'Boats.com',
            'logo' => '/logos/boats-com.svg',
            'status' => 'coming_soon',
            'description' => 'International boat listings',
            'always_on' => false,
        ],
        [
            'slug' => 'marktplaats',
            'name' => 'Marktplaats',
            'logo' => '/logos/marktplaats.svg',
            'status' => 'coming_soon',
            'description' => 'Dutch marketplace',
            'always_on' => false,
        ],
        [
            'slug' => 'botentekoop',
            'name' => 'Botentekoop.nl',
            'logo' => '/logos/botentekoop.svg',
            'status' => 'coming_soon',
            'description' => 'Dutch boat marketplace',
            'always_on' => false,
        ],
        [
            'slug' => 'theyachtmarket',
            'name' => 'TheYachtMarket',
            'logo' => '/logos/theyachtmarket.svg',
            'status' => 'coming_soon',
            'description' => 'UK yacht marketplace',
            'always_on' => false,
        ],
        [
            'slug' => 'inautia',
            'name' => 'iNautia',
            'logo' => '/logos/inautia.svg',
            'status' => 'coming_soon',
            'description' => 'Mediterranean boat portal',
            'always_on' => false,
        ],
        [
            'slug' => 'marine_hub',
            'name' => 'Marine Hub',
            'logo' => '/logos/marine-hub.svg',
            'status' => 'coming_soon',
            'description' => 'Marine equipment & boats',
            'always_on' => false,
        ],
    ];

    /**
     * GET /api/advertising-channels
     * Returns available advertising platforms.
     */
    public function index(): JsonResponse
    {
        return response()->json(self::PLATFORMS);
    }

    /**
     * POST /api/yachts/{id}/syndicate
     * Trigger syndication to selected channels.
     */
    public function syndicate($id): JsonResponse
    {
        $yacht = Yacht::findOrFail($id);
        $channels = $yacht->advertising_channels ?? [];
        $results = [];

        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'google_merchant':
                        $results[$channel] = $this->syndicateGoogleMerchant($yacht);
                        break;
                    case 'yachtshift':
                        $results[$channel] = $this->syndicateYachtshift($yacht);
                        break;
                    default:
                        $results[$channel] = ['status' => 'queued', 'message' => 'Integration coming soon'];
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Syndication to {$channel} failed for yacht {$id}: " . $e->getMessage());
                $results[$channel] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'yacht_id' => $yacht->id,
            'channels' => $results,
        ]);
    }

    /**
     * Google Merchant Center syndication via Content API.
     */
    private function syndicateGoogleMerchant(Yacht $yacht): array
    {
        $merchantId = env('GOOGLE_MERCHANT_ID');
        $apiKey = env('GOOGLE_MERCHANT_API_KEY');

        if (!$merchantId || !$apiKey) {
            return ['status' => 'skipped', 'message' => 'Google Merchant credentials not configured'];
        }

        // Build Google Merchant product data
        $product = [
            'offerId' => 'yacht-' . $yacht->id,
            'title' => $yacht->boat_name,
            'description' => $yacht->description ?? "Vessel: {$yacht->boat_name}",
            'link' => env('APP_FRONTEND_URL', 'https://schepen-kring.nl') . "/yachts/{$yacht->id}",
            'imageLink' => $yacht->main_image ? env('APP_URL') . '/storage/' . $yacht->main_image : null,
            'availability' => 'in_stock',
            'condition' => $yacht->year && $yacht->year < (date('Y') - 1) ? 'used' : 'new',
            'price' => [
                'value' => $yacht->price ?? '0',
                'currency' => 'EUR',
            ],
            'brand' => $yacht->builder ?? $yacht->boat_name,
            'productType' => 'Vehicles & Parts > Vehicles > Watercraft > Yachts',
            'customAttributes' => [
                ['name' => 'year', 'value' => (string)$yacht->year],
                ['name' => 'loa', 'value' => (string)$yacht->loa],
                ['name' => 'builder', 'value' => (string)$yacht->builder],
            ],
        ];

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post(
                "https://shoppingcontent.googleapis.com/content/v2.1/{$merchantId}/products",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $product,
                    'timeout' => 30,
                ]
            );

            return ['status' => 'success', 'message' => 'Published to Google Merchant'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Yachtshift syndication via OpenMarine XML.
     */
    private function syndicateYachtshift(Yacht $yacht): array
    {
        // Generate OpenMarine XML for this yacht
        $xml = $this->generateOpenMarineXml($yacht);

        // Store the XML feed file
        $filename = "feeds/yachtshift/yacht-{$yacht->id}.xml";
        \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $xml);

        $feedUrl = env('APP_URL') . '/storage/' . $filename;

        return [
            'status' => 'success',
            'message' => 'OpenMarine XML feed generated',
            'feed_url' => $feedUrl,
        ];
    }

    /**
     * Generate OpenMarine XML for a yacht.
     */
    private function generateOpenMarineXml(Yacht $yacht): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><open_marine/>');
        $xml->addAttribute('version', '1.0');

        $boat = $xml->addChild('boat');
        $boat->addChild('id', $yacht->id);
        $boat->addChild('name', htmlspecialchars($yacht->boat_name ?? ''));
        $boat->addChild('year', $yacht->year ?? '');
        $boat->addChild('price', $yacht->price ?? '');
        $boat->addChild('currency', 'EUR');
        $boat->addChild('status', $yacht->status ?? 'For Sale');

        // Dimensions
        $dimensions = $boat->addChild('dimensions');
        $dimensions->addChild('loa', $yacht->loa ?? '');
        $dimensions->addChild('beam', $yacht->beam ?? '');
        $dimensions->addChild('draft', $yacht->draft ?? '');
        $dimensions->addChild('displacement', $yacht->displacement ?? '');

        // Engine
        $engine = $boat->addChild('engine');
        $engine->addChild('manufacturer', htmlspecialchars($yacht->engine_manufacturer ?? ''));
        $engine->addChild('horsepower', $yacht->horse_power ?? '');
        $engine->addChild('fuel', $yacht->fuel ?? '');
        $engine->addChild('hours', $yacht->hours ?? '');

        // Construction
        $construction = $boat->addChild('construction');
        $construction->addChild('builder', htmlspecialchars($yacht->builder ?? ''));
        $construction->addChild('designer', htmlspecialchars($yacht->designer ?? ''));
        $construction->addChild('hull_type', $yacht->hull_type ?? '');
        $construction->addChild('hull_material', $yacht->hull_construction ?? '');

        // Images
        if ($yacht->main_image) {
            $images = $boat->addChild('images');
            $img = $images->addChild('image');
            $img->addChild('url', env('APP_URL') . '/storage/' . $yacht->main_image);
            $img->addChild('type', 'main');
        }

        // Description
        $boat->addChild('description', htmlspecialchars($yacht->description ?? ''));

        return $xml->asXML();
    }
}
