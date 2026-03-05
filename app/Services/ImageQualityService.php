<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ImageQualityService
{
    /**
     * Score an image for quality. Returns a score (0–100) and flag details.
     *
     * @param string $imagePath  Absolute filesystem path to the image
     * @return array ['score' => int, 'flags' => array, 'label' => string]
     */
    public function score(string $imagePath): array
    {
        $flags = [
            'too_dark'   => false,
            'too_bright' => false,
            'blurry'     => false,
            'low_res'    => false,
        ];

        $scores = [];

        try {
            if (extension_loaded('imagick')) {
                return $this->scoreWithImagick($imagePath);
            }

            return $this->scoreWithGD($imagePath);
        } catch (\Throwable $e) {
            Log::warning("Image quality scoring failed: " . $e->getMessage(), [
                'path' => $imagePath,
            ]);

            // Return a neutral score on failure
            return [
                'score' => 50,
                'flags' => $flags,
                'label' => '⚠️ Could not analyze',
            ];
        }
    }

    /**
     * Score using Imagick (more accurate).
     */
    protected function scoreWithImagick(string $imagePath): array
    {
        $imagick = new \Imagick($imagePath);
        $imagick->setImageColorspace(\Imagick::COLORSPACE_GRAY);

        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        // 1. Brightness check (mean luminance)
        $stats = $imagick->getImageStatistics();
        $meanLuminance = 128; // fallback
        if (isset($stats['Overall']['mean'])) {
            // Imagick mean is 0–65535, scale to 0–255
            $meanLuminance = $stats['Overall']['mean'] / 257;
        }

        // 2. Blur detection (Laplacian variance)
        $laplacian = clone $imagick;
        $laplacian->resizeImage(min($width, 500), 0, \Imagick::FILTER_LANCZOS, 1);
        $laplacian->convolveImage([0, 1, 0, 1, -4, 1, 0, 1, 0]);
        $lapStats = $laplacian->getImageStatistics();
        $lapVariance = 100; // fallback
        if (isset($lapStats['Overall']['standardDeviation'])) {
            $lapVariance = $lapStats['Overall']['standardDeviation'] / 257;
        }

        $laplacian->clear();
        $laplacian->destroy();
        $imagick->clear();
        $imagick->destroy();

        return $this->computeScore($meanLuminance, $lapVariance, $width, $height);
    }

    /**
     * Score using GD (fallback).
     */
    protected function scoreWithGD(string $imagePath): array
    {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return ['score' => 50, 'flags' => $this->defaultFlags(), 'label' => '⚠️ Could not analyze'];
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        // Load image
        $image = match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($imagePath),
            'image/png'               => imagecreatefrompng($imagePath),
            'image/webp'              => imagecreatefromwebp($imagePath),
            default                   => null,
        };

        if (!$image) {
            return ['score' => 50, 'flags' => $this->defaultFlags(), 'label' => '⚠️ Could not analyze'];
        }

        // Downsample for analysis (faster)
        $sampleWidth = min($width, 200);
        $sampleHeight = intval($height * ($sampleWidth / $width));
        $sample = imagecreatetruecolor($sampleWidth, $sampleHeight);
        imagecopyresampled($sample, $image, 0, 0, 0, 0, $sampleWidth, $sampleHeight, $width, $height);

        // 1. Brightness check (mean luminance via sampling)
        $totalLuminance = 0;
        $pixelCount = 0;

        for ($x = 0; $x < $sampleWidth; $x += 2) {
            for ($y = 0; $y < $sampleHeight; $y += 2) {
                $rgb = imagecolorat($sample, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                // ITU-R BT.709 luminance formula
                $totalLuminance += 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
                $pixelCount++;
            }
        }

        $meanLuminance = $pixelCount > 0 ? $totalLuminance / $pixelCount : 128;

        // 2. Blur detection (variance of Laplacian approximation via pixel differences)
        $lapSum = 0;
        $lapCount = 0;
        $grays = [];

        // Build grayscale grid
        for ($y = 0; $y < $sampleHeight; $y++) {
            for ($x = 0; $x < $sampleWidth; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $grays[$y][$x] = intval(0.299 * $r + 0.587 * $g + 0.114 * $b);
            }
        }

        // Laplacian kernel approximation
        for ($y = 1; $y < $sampleHeight - 1; $y++) {
            for ($x = 1; $x < $sampleWidth - 1; $x++) {
                $lap = abs(
                    -4 * $grays[$y][$x]
                    + $grays[$y - 1][$x]
                    + $grays[$y + 1][$x]
                    + $grays[$y][$x - 1]
                    + $grays[$y][$x + 1]
                );
                $lapSum += $lap * $lap;
                $lapCount++;
            }
        }

        $lapVariance = $lapCount > 0 ? sqrt($lapSum / $lapCount) : 100;

        imagedestroy($image);
        imagedestroy($sample);

        return $this->computeScore($meanLuminance, $lapVariance, $width, $height);
    }

    /**
     * Compute final score from raw metrics.
     */
    protected function computeScore(float $meanLuminance, float $lapVariance, int $width, int $height): array
    {
        $flags = [
            'too_dark'   => $meanLuminance < 40,
            'too_bright' => $meanLuminance > 220,
            'blurry'     => $lapVariance < 8,
            'low_res'    => $width < 800,
        ];

        // Score components (each 0–25, total 0–100)
        // Brightness score (ideal 80–180)
        if ($meanLuminance < 40) {
            $brightnessScore = max(0, $meanLuminance / 40 * 15);
        } elseif ($meanLuminance > 220) {
            $brightnessScore = max(0, (255 - $meanLuminance) / 35 * 15);
        } else {
            $brightnessScore = 25;
        }

        // Sharpness score (higher Laplacian variance = sharper)
        $sharpnessScore = min(25, $lapVariance / 20 * 25);

        // Resolution score
        if ($width >= 2000) {
            $resScore = 25;
        } elseif ($width >= 800) {
            $resScore = 15 + ($width - 800) / 1200 * 10;
        } else {
            $resScore = max(0, $width / 800 * 15);
        }

        // Aspect ratio bonus (reasonable photos are not extremely narrow/wide)
        $ratio = $width > 0 ? $height / $width : 1;
        $aspectScore = ($ratio > 0.3 && $ratio < 3) ? 25 : 15;

        $totalScore = intval(round($brightnessScore + $sharpnessScore + $resScore + $aspectScore));
        $totalScore = max(0, min(100, $totalScore));

        // Determine label
        $flagCount = count(array_filter($flags));

        if ($flagCount === 0 && $totalScore >= 70) {
            $label = '✅ Great';
        } elseif ($flags['blurry']) {
            $label = '❌ Too blurry';
        } elseif ($flags['too_dark']) {
            $label = '⚠️ Low light';
        } elseif ($flags['too_bright']) {
            $label = '⚠️ Overexposed';
        } elseif ($flags['low_res']) {
            $label = '⚠️ Low resolution';
        } elseif ($totalScore >= 50) {
            $label = '✅ Acceptable';
        } else {
            $label = '⚠️ Low quality';
        }

        return [
            'score' => $totalScore,
            'flags' => $flags,
            'label' => $label,
        ];
    }

    /**
     * Default flags array.
     */
    protected function defaultFlags(): array
    {
        return [
            'too_dark'   => false,
            'too_bright' => false,
            'blurry'     => false,
            'low_res'    => false,
        ];
    }
}
