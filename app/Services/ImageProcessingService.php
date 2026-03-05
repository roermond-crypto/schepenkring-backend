<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageProcessingService
{
    /**
     * Process a single image: EXIF rotate, resize, export WebP master + thumb.
     *
     * @param string $inputPath  Absolute path to the original temp image
     * @param string $outputDir  Relative storage path for output (e.g. "approved/master/")
     * @param string $thumbDir   Relative storage path for thumbnails (e.g. "approved/thumb/")
     * @param int    $maxWidth   Max width for master output
     * @return array ['master_path' => string, 'thumb_path' => string, 'width' => int, 'height' => int]
     */
    public function process(string $inputPath, string $outputDir, string $thumbDir, int $maxWidth = 2560): array
    {
        $useImagick = extension_loaded('imagick');

        Log::info("[ImageProcessing] Processing image: {$inputPath}", [
            'engine'   => $useImagick ? 'Imagick' : 'GD',
            'filesize' => file_exists($inputPath) ? filesize($inputPath) : 'NOT FOUND',
        ]);

        if ($useImagick) {
            return $this->processWithImagick($inputPath, $outputDir, $thumbDir, $maxWidth);
        }

        return $this->processWithGD($inputPath, $outputDir, $thumbDir, $maxWidth);
    }

    /**
     * Process using Imagick (preferred — better quality, EXIF support).
     */
    protected function processWithImagick(string $inputPath, string $outputDir, string $thumbDir, int $maxWidth): array
    {
        $imagick = new \Imagick($inputPath);

        // 1. EXIF auto-rotate
        $orientation = $imagick->getImageOrientation();
        Log::info("[ImageProcessing][Imagick] EXIF orientation: {$orientation}");
        $this->autoRotateImagick($imagick, $orientation);

        // 2. Strip EXIF metadata (smaller file, no residual rotation tags)
        $imagick->stripImage();

        // 3. Resize master (maintain aspect ratio)
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if ($width > $maxWidth) {
            $imagick->resizeImage($maxWidth, 0, \Imagick::FILTER_LANCZOS, 1);
        }

        // 4. Export master as WebP
        $imagick->setImageFormat('webp');
        $imagick->setImageCompressionQuality(82);

        $masterFilename = uniqid('opt_') . '.webp';
        $masterPath = $outputDir . '/' . $masterFilename;
        $masterAbsPath = storage_path('app/public/' . $masterPath);

        $dir = dirname($masterAbsPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $imagick->writeImage($masterAbsPath);

        // 5. Generate thumbnail (320px wide)
        $thumbImagick = clone $imagick;
        $thumbImagick->resizeImage(320, 0, \Imagick::FILTER_LANCZOS, 1);

        $thumbFilename = uniqid('thumb_') . '.webp';
        $thumbPath = $thumbDir . '/' . $thumbFilename;
        $thumbAbsPath = storage_path('app/public/' . $thumbPath);

        $thumbDirAbs = dirname($thumbAbsPath);
        if (!is_dir($thumbDirAbs)) {
            mkdir($thumbDirAbs, 0755, true);
        }

        $thumbImagick->writeImage($thumbAbsPath);

        $finalWidth = $imagick->getImageWidth();
        $finalHeight = $imagick->getImageHeight();

        $imagick->clear();
        $imagick->destroy();
        $thumbImagick->clear();
        $thumbImagick->destroy();

        Log::info("[ImageProcessing][Imagick] Done: {$masterPath} ({$finalWidth}x{$finalHeight})");

        return [
            'master_path' => $masterPath,
            'thumb_path'  => $thumbPath,
            'width'       => $finalWidth,
            'height'      => $finalHeight,
        ];
    }

    /**
     * Process using GD (fallback — works on most PHP installs).
     */
    protected function processWithGD(string $inputPath, string $outputDir, string $thumbDir, int $maxWidth): array
    {
        // Detect image type
        $imageInfo = getimagesize($inputPath);
        if (!$imageInfo) {
            throw new \RuntimeException("Cannot read image: {$inputPath}");
        }

        $mimeType = $imageInfo['mime'];
        Log::info("[ImageProcessing][GD] MIME type: {$mimeType}, dimensions: {$imageInfo[0]}x{$imageInfo[1]}");

        // ── 1. Read EXIF orientation BEFORE loading into GD ──
        // EXIF data only exists in JPEG files. For other formats rotation is not an issue
        // because they don't carry EXIF orientation tags.
        $exifOrientation = 1; // default = normal
        if (function_exists('exif_read_data')) {
            // Try to read EXIF regardless of extension — the file might be JPEG with wrong extension
            try {
                $exif = @exif_read_data($inputPath);
                if ($exif && isset($exif['Orientation'])) {
                    $exifOrientation = (int) $exif['Orientation'];
                    Log::info("[ImageProcessing][GD] EXIF orientation found: {$exifOrientation}");
                } else {
                    Log::info("[ImageProcessing][GD] No EXIF orientation tag found (normal for non-JPEG)");
                }
            } catch (\Throwable $e) {
                Log::info("[ImageProcessing][GD] EXIF read failed (expected for non-JPEG): " . $e->getMessage());
            }
        } else {
            Log::warning("[ImageProcessing][GD] exif_read_data() not available — EXIF rotation disabled!");
        }

        // ── Load image into GD ──
        $srcImage = $this->loadImageGD($inputPath, $mimeType);

        if (!$srcImage) {
            throw new \RuntimeException("Unsupported image format: {$mimeType}");
        }

        // ── Apply EXIF rotation ──
        if ($exifOrientation > 1) {
            Log::info("[ImageProcessing][GD] Applying rotation for EXIF orientation {$exifOrientation}");
            $srcImage = $this->autoRotateGD($srcImage, $exifOrientation);
        }

        $width = imagesx($srcImage);
        $height = imagesy($srcImage);
        Log::info("[ImageProcessing][GD] After rotation: {$width}x{$height}");

        // ── 3. Resize if needed ──
        if ($width > $maxWidth) {
            $newHeight = intval($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($maxWidth, $newHeight);
            imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            imagedestroy($srcImage);
            $srcImage = $resized;
            $width = $maxWidth;
            $height = $newHeight;
        }

        // ── 4. Export master as WebP ──
        // Convert palette images to true-color (required for WebP export)
        if (!imageistruecolor($srcImage)) {
            imagepalettetotruecolor($srcImage);
            Log::info("[ImageProcessing][GD] Converted palette image to true-color");
        }
        imagealphablending($srcImage, true);
        imagesavealpha($srcImage, true);

        $masterFilename = uniqid('opt_') . '.webp';
        $masterPath = $outputDir . '/' . $masterFilename;
        $masterAbsPath = storage_path('app/public/' . $masterPath);

        $dir = dirname($masterAbsPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        imagewebp($srcImage, $masterAbsPath, 82);

        // ── 5. Generate thumbnail (320px wide) ──
        $thumbWidth = 320;
        $thumbHeight = intval($height * ($thumbWidth / $width));
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
        imagecopyresampled($thumbImage, $srcImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        $thumbFilename = uniqid('thumb_') . '.webp';
        $thumbPath = $thumbDir . '/' . $thumbFilename;
        $thumbAbsPath = storage_path('app/public/' . $thumbPath);

        $thumbDirAbs = dirname($thumbAbsPath);
        if (!is_dir($thumbDirAbs)) {
            mkdir($thumbDirAbs, 0755, true);
        }

        imagewebp($thumbImage, $thumbAbsPath, 80);

        $finalWidth = imagesx($srcImage);
        $finalHeight = imagesy($srcImage);

        imagedestroy($srcImage);
        imagedestroy($thumbImage);

        Log::info("[ImageProcessing][GD] Done: {$masterPath} ({$finalWidth}x{$finalHeight})");

        return [
            'master_path' => $masterPath,
            'thumb_path'  => $thumbPath,
            'width'       => $finalWidth,
            'height'      => $finalHeight,
        ];
    }

    /**
     * Load an image with GD based on mime type.
     */
    protected function loadImageGD(string $path, string $mimeType)
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
            'image/png'               => imagecreatefrompng($path),
            'image/webp'              => imagecreatefromwebp($path),
            'image/gif'               => imagecreatefromgif($path),
            default                   => null,
        };
    }

    /**
     * Auto-rotate using Imagick orientation.
     */
    protected function autoRotateImagick(\Imagick $image, int $orientation): void
    {
        switch ($orientation) {
            case \Imagick::ORIENTATION_TOPRIGHT:    // 2 — mirror horizontal
                $image->flopImage();
                break;
            case \Imagick::ORIENTATION_BOTTOMRIGHT: // 3 — rotate 180
                $image->rotateImage('#000', 180);
                break;
            case \Imagick::ORIENTATION_BOTTOMLEFT:  // 4 — mirror vertical
                $image->flipImage();
                break;
            case \Imagick::ORIENTATION_LEFTTOP:     // 5 — mirror horizontal + 270 CW
                $image->flopImage();
                $image->rotateImage('#000', -90);
                break;
            case \Imagick::ORIENTATION_RIGHTTOP:     // 6 — rotate 90 CW
                $image->rotateImage('#000', 90);
                break;
            case \Imagick::ORIENTATION_RIGHTBOTTOM:  // 7 — mirror horizontal + 90 CW
                $image->flopImage();
                $image->rotateImage('#000', 90);
                break;
            case \Imagick::ORIENTATION_LEFTBOTTOM:   // 8 — rotate 270 CW (= 90 CCW)
                $image->rotateImage('#000', -90);
                break;
        }
        $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    /**
     * Auto-rotate using GD + EXIF orientation.
     * Handles all 8 EXIF orientation values.
     */
    protected function autoRotateGD($image, int $orientation)
    {
        switch ($orientation) {
            case 2: // mirror horizontal
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return $image;
            case 3: // rotate 180
                return imagerotate($image, 180, 0);
            case 4: // mirror vertical
                imageflip($image, IMG_FLIP_VERTICAL);
                return $image;
            case 5: // mirror horizontal + 270 CW
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return imagerotate($image, 90, 0);
            case 6: // rotate 90 CW
                return imagerotate($image, -90, 0);
            case 7: // mirror horizontal + 90 CW
                imageflip($image, IMG_FLIP_HORIZONTAL);
                return imagerotate($image, -90, 0);
            case 8: // rotate 270 CW (= 90 CCW)
                return imagerotate($image, 90, 0);
            default: // 1 = normal, no rotation
                return $image;
        }
    }
}
