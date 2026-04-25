<?php

namespace App\Services;

use App\Models\Yacht;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class StickerService
{
    private const STICKER_WIDTH_POINTS = 840.0;
    private const STICKER_HEIGHT_POINTS = 390.0;

    /**
     * Ensure the yacht has an up-to-date public URL and QR code asset.
     */
    public function syncForYacht(Yacht $yacht, bool $force = false): Yacht
    {
        $publicUrl = $this->generatePublicUrl($yacht);
        $currentQrCodePath = $yacht->qr_code_path;
        $hasStoredQrCode = filled($currentQrCodePath)
            && Storage::disk('public')->exists($currentQrCodePath);
        $urlChanged = $yacht->public_url !== $publicUrl;

        if (!$force && !$urlChanged && filled($yacht->public_url) && $hasStoredQrCode) {
            return $yacht->refresh();
        }

        if ($hasStoredQrCode && ($force || $urlChanged)) {
            Storage::disk('public')->delete($currentQrCodePath);
        }

        $qrCodePath = $this->generateQrCode($yacht, $publicUrl);

        $yacht->forceFill([
            'public_url' => $publicUrl,
            'qr_code_path' => $qrCodePath,
        ])->saveQuietly();

        return $yacht->refresh();
    }

    /**
     * Generate the frontend public listing URL for the yacht.
     */
    public function generatePublicUrl(Yacht $yacht, string $locale = 'nl'): string
    {
        $baseUrl = config('app.frontend_url', 'https://app.schepen-kring.nl');
        $slug = $this->buildSlug($yacht);
        
        return rtrim($baseUrl, '/') . "/{$locale}/yachts/{$yacht->id}/{$slug}";
    }

    /**
     * Build slug based on yacht properties.
     */
    public function buildSlug(Yacht $yacht): string
    {
        $placeholders = ['-', '—', '–', 'n/a', 'na', 'null', 'undefined'];
        
        $candidates = [
            $yacht->boat_name,
            trim(($yacht->manufacturer ?? '') . ' ' . ($yacht->model ?? '')),
            $yacht->vessel_id,
            "yacht-{$yacht->id}"
        ];

        foreach ($candidates as $candidate) {
            if (!$candidate) continue;
            
            $trimmed = trim($candidate);
            if (in_array(strtolower($trimmed), $placeholders)) continue;

            $slug = Str::slug($trimmed);
            if ($slug) return $slug;
        }

        return 'details';
    }

    /**
     * Generate QR code image and save to storage.
     */
    public function generateQrCode(Yacht $yacht, string $url): string
    {
        $directory = 'qrcodes';
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $filename = "qr_{$yacht->id}_" . Str::random(12) . ".svg";
        $path = "{$directory}/{$filename}";

        $image = QrCode::format('svg')
            ->size(500)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($url);

        Storage::disk('public')->put($path, $image);

        return $path;
    }

    public function getQrCodePreviewUrl(Yacht $yacht): string
    {
        if (!$yacht->qr_code_path) return '';
        return Storage::disk('public')->url($yacht->qr_code_path);
    }

    public function getQrCodeDataUri(Yacht $yacht): string
    {
        if (!$yacht->qr_code_path) return '';
        
        $contents = Storage::disk('public')->get($yacht->qr_code_path);
        $extension = strtolower((string) pathinfo($yacht->qr_code_path, PATHINFO_EXTENSION));
        $mimeType = $extension === 'svg' ? 'image/svg+xml' : 'image/png';

        return "data:{$mimeType};base64," . base64_encode($contents);
    }

    public function getStickerViewData(Yacht $yacht, bool $forPdf = false): array
    {
        return [
            'yacht' => $yacht,
            'qrCodeSrc' => $forPdf
                ? $this->getQrCodeDataUri($yacht)
                : $this->getQrCodePreviewUrl($yacht),
            'publicUrl' => $yacht->public_url,
        ];
    }

    /**
     * Generate Sticker PDF.
     */
    public function generatePdf(Yacht $yacht)
    {
        $yacht = $this->syncForYacht($yacht);

        return Pdf::loadView('stickers.boat', $this->getStickerViewData($yacht, true))
            ->setPaper([
                0,
                0,
                self::STICKER_WIDTH_POINTS,
                self::STICKER_HEIGHT_POINTS,
            ]);
    }
}
