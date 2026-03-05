<?php

namespace App\Services\Merchant;

use App\Models\Yacht;
use Illuminate\Support\Str;

class ProductMapper
{
    /**
     * Determine if a yacht is eligible to be listed on Google Merchant.
     */
    public function isEligible(Yacht $yacht): bool
    {
        if (!in_array($yacht->status, ['For Sale', 'For Bid'])) {
            return false;
        }

        // Must have a valid price
        if (empty($yacht->price) || $yacht->price <= 0) {
            return false;
        }

        // Must have at least a main image
        if (empty($yacht->main_image)) {
            return false;
        }

        return true;
    }

    /**
     * Map a Yacht model to a Google Merchant Center product array.
     */
    public function mapYachtToGoogleProduct(Yacht $yacht): array
    {
        $domain = rtrim(config('app.url', 'https://schepen-kring.nl'), '/');

        // Canonical URL + UTM tracking
        $slug = $yacht->slug ?: Str::slug($yacht->boat_name);
        $canonicalUrl = "{$domain}/nl/yachts/{$yacht->id}/{$slug}";
        $link = "{$canonicalUrl}?utm_source=google&utm_medium=free_listings&utm_campaign=merchant";

        // Determine deterministic title: "Brand Model (Year) - Length - Type"
        $brand = $yacht->manufacturer ?? $yacht->construction?->builder ?? 'Unknown Brand';
        $model = $yacht->model ?? '';
        $year = $yacht->year ? "({$yacht->year})" : '';
        $length = $yacht->loa ? "{$yacht->loa}m" : '';

        // Clean up title parts and join them
        $titleParts = array_filter([trim("$brand $model"), $year, '-', $length]);
        $title = implode(' ', $titleParts);
        
        // Google requires max 150 chars, but recommends < 70
        $title = Str::limit($title, 145);

        // Sanitize description
        $description = $yacht->short_description_en ?? $yacht->short_description_nl ?? $title;
        $description = strip_tags($description);
        // Remove phone numbers or emails roughly
        $description = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '', $description); // remove emails
        $description = preg_replace('/\+?[0-9][0-9()\-\s+]{7,20}[0-9]/', '', $description); // remove phones
        $description = Str::limit(trim($description), 4900); // Max 5000 chars

        // Images setup
        $imageLink = $this->getImageAbsoluteUrl($yacht->main_image, $domain);
        
        $additionalImageLinks = [];
        if (!empty($yacht->gallery_images) && is_array($yacht->gallery_images)) {
            foreach ($yacht->gallery_images as $img) {
                $additionalImageLinks[] = $this->getImageAbsoluteUrl($img, $domain);
            }
            // Google limits to 10 additional images
            $additionalImageLinks = array_slice($additionalImageLinks, 0, 10);
        }

        // Offer ID must be stable and unique. We use the yacht ID.
        $offerId = strval($yacht->id);

        // Availability logic
        $availability = in_array($yacht->status, ['For Sale', 'For Bid']) ? 'in stock' : 'out of stock';

        $payload = [
            'offerId' => $offerId,
            'title' => $title,
            'description' => $description,
            'link' => $link,
            'imageLink' => $imageLink,
            'contentLanguage' => 'nl', // assuming dutch primarily
            'targetCountry' => 'NL',
            'channel' => 'online',
            'availability' => $availability,
            'condition' => 'used', // Default condition for broker boats
            'price' => [
                'value' => number_format((float) $yacht->price, 2, '.', ''),
                'currency' => 'EUR', // Assuming EUR
            ],
            'brand' => Str::limit($brand, 70), // Google limits brand to 70 chars
        ];

        // Only add additionalImageLinks if they exist
        if (!empty($additionalImageLinks)) {
            $payload['additionalImageLinks'] = $additionalImageLinks;
        }

        return $payload;
    }

    /**
     * Ensure the image is an absolute URL pointing to our storage.
     */
    protected function getImageAbsoluteUrl(string $path, string $domain): string
    {
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        
        // Clean leading slash
        $path = ltrim($path, '/');
        
        return "{$domain}/storage/{$path}";
    }
}
