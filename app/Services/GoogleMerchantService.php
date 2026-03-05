<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMerchantService
{
    protected ?string $merchantId;
    protected string $baseUrl = 'https://shoppingcontent.googleapis.com/content/v2.1';
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->merchantId = config('services.google_merchant.id', env('GOOGLE_MERCHANT_ACCOUNT_ID', 'STUB_MERCHANT_ID'));
    }

    /**
     * Get or refresh an OAuth 2.0 access token using a service account JSON file.
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $credentialsPath = config('services.google_merchant.credentials_path', env('GOOGLE_APPLICATION_CREDENTIALS'));

        if (!$credentialsPath || !file_exists($credentialsPath)) {
            Log::warning('Google Merchant API: GOOGLE_APPLICATION_CREDENTIALS path is invalid or missing.');
            // For testing/stubbing local development without credentials:
            return 'STUB_TOKEN_FOR_LOCAL_DEV';
        }

        try {
            // Using the official Google API Client if installed locally
            $client = new \Google_Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope('https://www.googleapis.com/auth/content');
            $client->fetchAccessTokenWithAssertion();
            
            $token = $client->getAccessToken();
            $this->accessToken = $token['access_token'] ?? null;

            if (!$this->accessToken) {
                throw new Exception('Failed to obtain Google Merchant access token.');
            }

            return $this->accessToken;

        } catch (Exception $e) {
            Log::error('Google Merchant Auth Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upsert a product into Google Merchant Center.
     * https://developers.google.com/shopping-content/reference/rest/v2.1/products/insert
     */
    public function upsertProduct(array $productPayload): ?array
    {
        $token = $this->getAccessToken();
        
        // For local development without real credentials, return a dummy success response
        if ($token === 'STUB_TOKEN_FOR_LOCAL_DEV') {
            Log::info('Google Merchant API STUB: Upserted product ' . $productPayload['offerId'], $productPayload);
            return ['id' => 'online:en:US:' . $productPayload['offerId'], 'kind' => 'content#product'];
        }

        $url = "{$this->baseUrl}/{$this->merchantId}/products";

        $response = Http::withToken($token)
            ->post($url, $productPayload);

        if ($response->failed()) {
            throw new Exception("Google Merchant API Error (Upsert): " . $response->body());
        }

        return $response->json();
    }

    /**
     * Set a product out of stock without deleting it completely.
     */
    public function setOutOfStock(string $offerId, string $targetCountry = 'US', string $contentLanguage = 'en'): ?array
    {
        $token = $this->getAccessToken();
        $productId = "online:{$contentLanguage}:{$targetCountry}:{$offerId}";

        if ($token === 'STUB_TOKEN_FOR_LOCAL_DEV') {
            Log::info('Google Merchant API STUB: Set Out of Stock ' . $productId);
            return ['id' => $productId, 'availability' => 'out of stock'];
        }

        $url = "{$this->baseUrl}/{$this->merchantId}/products/{$productId}";

        // As per the Google Shopping specs, to just update availability, we patch the product
        // Or re-submit with the exact same data but availability="out of stock"
        // Google Content API has a custom batch update or we just patch:
        // Note: Content API doesn't strictly have a PATCH endpoint for products, so custom batch or full payload is usually required.
        // For simplicity, we assume we might need the full payload or we use the local inventory update API.

        throw new Exception('Partial updating "out_of_stock" directly via REST without full payload is complex. Best approach: re-run ProductMapper with Yacht set to out of stock and upsert.');
    }

    /**
     * Delete a product from Google Merchant Center.
     */
    public function deleteProduct(string $offerId, string $targetCountry = 'US', string $contentLanguage = 'en'): bool
    {
        $token = $this->getAccessToken();
        $productId = "online:{$contentLanguage}:{$targetCountry}:{$offerId}";

        if ($token === 'STUB_TOKEN_FOR_LOCAL_DEV') {
            Log::info('Google Merchant API STUB: Deleted product ' . $productId);
            return true;
        }

        $url = "{$this->baseUrl}/{$this->merchantId}/products/{$productId}";

        $response = Http::withToken($token)->delete($url);

        if ($response->failed()) {
            // If it returns 404, the product is already gone, which we can consider a success for delete operations
            if ($response->status() === 404) {
                return true;
            }
            throw new Exception("Google Merchant API Error (Delete): " . $response->body());
        }

        return true;
    }
}
