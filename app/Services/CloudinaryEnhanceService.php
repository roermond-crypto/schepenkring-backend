<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudinaryEnhanceService
{
    private ?Cloudinary $cloudinary = null;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = (bool) config('services.cloudinary.enhance_enabled', false);

        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        if ($this->enabled && $cloudName && $apiKey && $apiSecret) {
            $this->cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key'    => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => [
                    'secure' => true,
                ],
            ]);
        } else {
            $this->enabled = false;
            Log::info('[CloudinaryEnhance] Disabled — missing credentials or CLOUDINARY_ENHANCE_ENABLED=false');
        }
    }

    /**
     * Check if Cloudinary enhancement is available.
     */
    public function isAvailable(): bool
    {
        return $this->enabled && $this->cloudinary !== null;
    }

    /**
     * Enhance an image using Cloudinary AI transformations.
     *
     * Pipeline: upload → e_improve → e_auto_color → optional e_upscale → download → delete remote
     *
     * @param string $localPath    Absolute path to the image file
     * @param array  $qualityFlags Quality flags from ImageQualityService
     * @param int    $rotationAngle Explicit rotation angle (0, 90, 180, 270)
     * @return string|null         Absolute path to the enhanced local file, or null on failure
     */
    public function enhance(string $localPath, array $qualityFlags = [], int $rotationAngle = 0): ?string
    {
        if (!$this->isAvailable()) {
            Log::info('[CloudinaryEnhance] Skipping — service not available');
            return null;
        }

        $startTime = microtime(true);

        try {
            // ── Build eager transformation ──
            $eagerParts = ['e_improve:outdoor', 'e_auto_color'];

            if ($rotationAngle > 0 && in_array($rotationAngle, [90, 180, 270])) {
                $eagerParts[] = "a_{$rotationAngle}";
                Log::info("[CloudinaryEnhance] Applying explicit AI rotation: {$rotationAngle} degrees");
            }

            // Conditional upscale for low-res images
            if (!empty($qualityFlags['low_res'])) {
                $eagerParts[] = 'e_upscale';
                Log::info('[CloudinaryEnhance] Adding upscale for low-res image');
            }

            $eagerTransform = implode(',', $eagerParts) . ',q_auto:best,f_jpg';

            Log::info('[CloudinaryEnhance] Uploading with eager transform...', [
                'file'  => basename($localPath),
                'size'  => filesize($localPath),
                'eager' => $eagerTransform,
            ]);

            // ── 1. Upload + process in one call ──
            $uploadResult = $this->cloudinary->uploadApi()->upload($localPath, [
                'resource_type' => 'image',
                'folder'        => 'nauticsecure_temp',
                'overwrite'     => true,
                'eager'         => [$eagerTransform],
                'eager_async'   => false,
            ]);

            $publicId = $uploadResult['public_id'];

            Log::info('[CloudinaryEnhance] Upload + transform done', [
                'public_id' => $publicId,
                'width'     => $uploadResult['width'] ?? 0,
                'height'    => $uploadResult['height'] ?? 0,
            ]);

            // ── 2. Get the enhanced URL from eager result ──
            $enhancedUrl = null;

            if (isset($uploadResult['eager'][0]['secure_url'])) {
                $enhancedUrl = $uploadResult['eager'][0]['secure_url'];
            } elseif (isset($uploadResult['eager'][0]['url'])) {
                $enhancedUrl = str_replace('http://', 'https://', $uploadResult['eager'][0]['url']);
            }

            if (!$enhancedUrl) {
                Log::error('[CloudinaryEnhance] No eager URL returned', [
                    'eager' => $uploadResult['eager'] ?? 'missing',
                ]);
                $this->cleanupRemote($publicId);
                return null;
            }

            Log::info('[CloudinaryEnhance] Downloading enhanced image', [
                'url' => $enhancedUrl,
            ]);

            // ── 3. Download enhanced image back to local storage ──
            $response = Http::timeout(60)->get($enhancedUrl);

            if (!$response->successful()) {
                Log::error('[CloudinaryEnhance] Failed to download enhanced image', [
                    'status' => $response->status(),
                    'url'    => $enhancedUrl,
                ]);
                $this->cleanupRemote($publicId);
                return null;
            }

            $enhancedPath = dirname($localPath) . '/' . uniqid('enhanced_') . '.jpg';
            file_put_contents($enhancedPath, $response->body());

            $elapsed = round(microtime(true) - $startTime, 2);

            Log::info('[CloudinaryEnhance] ✅ Enhancement complete!', [
                'output'  => basename($enhancedPath),
                'size'    => filesize($enhancedPath),
                'elapsed' => "{$elapsed}s",
            ]);

            // ── 4. Delete from Cloudinary (no remote storage cost) ──
            $this->cleanupRemote($publicId);

            return $enhancedPath;

        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $startTime, 2);
            Log::error('[CloudinaryEnhance] Enhancement failed', [
                'error'   => $e->getMessage(),
                'elapsed' => "{$elapsed}s",
            ]);
            return null;
        }
    }

    /**
     * Delete an image from Cloudinary to avoid storage costs.
     */
    private function cleanupRemote(string $publicId): void
    {
        try {
            $this->cloudinary->adminApi()->deleteAssets([$publicId]);
            Log::info('[CloudinaryEnhance] Cleaned up remote: ' . $publicId);
        } catch (\Throwable $e) {
            Log::warning('[CloudinaryEnhance] Failed to cleanup remote', [
                'public_id' => $publicId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
