<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FFmpegService
{
    private string $ffmpegBin;

    public function __construct()
    {
        $this->ffmpegBin = env('FFMPEG_BINARY', '/usr/bin/ffmpeg');
    }

    /**
     * Render a slideshow MP4 from a list of image paths.
     * Each image is shown for $secondsPerImage seconds.
     * Output: H264 1920x1080 @ 30fps.
     */
    public function renderSlideshow(array $imagePaths, string $outputPath, int $secondsPerImage = 3): string
    {
        // Create a temp directory for numbered images
        $tempDir = sys_get_temp_dir() . '/ffmpeg_' . uniqid();
        mkdir($tempDir, 0777, true);

        // Copy/convert images to temp dir with sequential numbering
        foreach ($imagePaths as $idx => $path) {
            $num = str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $dest = "{$tempDir}/img_{$num}.{$ext}";

            if (file_exists($path)) {
                copy($path, $dest);
            } elseif (Storage::disk('public')->exists($path)) {
                copy(Storage::disk('public')->path($path), $dest);
            }
        }

        // Build FFmpeg command for slideshow with zoom effect
        $duration = count($imagePaths) * $secondsPerImage;
        $framerate = "1/{$secondsPerImage}";

        // Simple slideshow (compatible with all image formats)
        $cmd = sprintf(
            '%s -y -framerate %s -pattern_type glob -i "%s/img_*.*" ' .
            '-vf "scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2:black,format=yuv420p" ' .
            '-c:v libx264 -preset medium -crf 23 -r 30 -pix_fmt yuv420p -t %d "%s" 2>&1',
            $this->ffmpegBin,
            $framerate,
            $tempDir,
            $duration,
            $outputPath
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        // Cleanup temp dir
        array_map('unlink', glob("{$tempDir}/*"));
        rmdir($tempDir);

        if ($returnCode !== 0) {
            throw new \RuntimeException("FFmpeg slideshow failed (code {$returnCode}): " . implode("\n", array_slice($output, -10)));
        }

        return $outputPath;
    }

    /**
     * Add background music to a video.
     * Music volume is reduced if voiceover is present.
     */
    public function addBackgroundMusic(string $videoPath, string $musicPath, string $outputPath): string
    {
        $cmd = sprintf(
            '%s -y -i "%s" -i "%s" ' .
            '-c:v copy -c:a aac -b:a 128k -shortest "%s" 2>&1',
            $this->ffmpegBin,
            $videoPath,
            $musicPath,
            $outputPath
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException("FFmpeg music failed (code {$returnCode}): " . implode("\n", array_slice($output, -10)));
        }

        return $outputPath;
    }

    /**
     * Mix background music + voiceover together with a video.
     * Music at 30% volume, voice at 100%.
     */
    public function mixAudioTracks(string $videoPath, string $musicPath, string $voicePath, string $outputPath): string
    {
        $cmd = sprintf(
            '%s -y -i "%s" -i "%s" -i "%s" ' .
            '-filter_complex "[1:a]volume=0.3[music];[2:a]volume=1.0[voice];[music][voice]amix=inputs=2:duration=shortest[a]" ' .
            '-map 0:v -map "[a]" -c:v copy -c:a aac -b:a 192k -shortest "%s" 2>&1',
            $this->ffmpegBin,
            $videoPath,
            $musicPath,
            $voicePath,
            $outputPath
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException("FFmpeg audio mix failed (code {$returnCode}): " . implode("\n", array_slice($output, -10)));
        }

        return $outputPath;
    }

    /**
     * Add a watermark logo to the bottom-right corner.
     */
    public function addWatermark(string $videoPath, string $logoPath, string $outputPath): string
    {
        $cmd = sprintf(
            '%s -y -i "%s" -i "%s" ' .
            '-filter_complex "overlay=W-w-20:H-h-20" ' .
            '-c:v libx264 -preset medium -crf 23 -c:a copy "%s" 2>&1',
            $this->ffmpegBin,
            $videoPath,
            $logoPath,
            $outputPath
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException("FFmpeg watermark failed (code {$returnCode}): " . implode("\n", array_slice($output, -10)));
        }

        return $outputPath;
    }

    /**
     * Get video duration in seconds.
     */
    public function getDuration(string $videoPath): int
    {
        $cmd = sprintf(
            '%s -i "%s" 2>&1 | grep "Duration"',
            $this->ffmpegBin,
            $videoPath
        );

        $output = [];
        exec($cmd, $output);
        $line = $output[0] ?? '';

        if (preg_match('/Duration: (\d{2}):(\d{2}):(\d{2})/', $line, $m)) {
            return ($m[1] * 3600) + ($m[2] * 60) + $m[3];
        }

        return 0;
    }

    /**
     * Check if FFmpeg is installed and accessible.
     */
    public function isAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        exec("{$this->ffmpegBin} -version 2>&1", $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Render a vertical slideshow (1080x1920) with optional overlay text lines.
     */
    public function renderVerticalSlideshow(array $imagePaths, string $outputPath, int $secondsPerImage = 2, array $overlayLines = []): string
    {
        $tempDir = sys_get_temp_dir() . '/ffmpeg_vertical_' . uniqid();
        mkdir($tempDir, 0777, true);

        foreach ($imagePaths as $idx => $path) {
            $num = str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $dest = "{$tempDir}/img_{$num}.{$ext}";

            if (file_exists($path)) {
                copy($path, $dest);
            } elseif (Storage::disk('public')->exists($path)) {
                copy(Storage::disk('public')->path($path), $dest);
            }
        }

        $duration = count($imagePaths) * $secondsPerImage;
        $framerate = "1/{$secondsPerImage}";

        $overlayFilter = $this->buildOverlayFilter($overlayLines);
        $filter = 'scale=1080:1920:force_original_aspect_ratio=decrease,' .
            'pad=1080:1920:(ow-iw)/2:(oh-ih)/2:black,format=yuv420p' .
            $overlayFilter;

        $cmd = sprintf(
            '%s -y -framerate %s -pattern_type glob -i "%s/img_*.*" ' .
            '-vf "%s" -c:v libx264 -preset medium -crf 23 -r 30 -pix_fmt yuv420p -t %d "%s" 2>&1',
            $this->ffmpegBin,
            $framerate,
            $tempDir,
            $filter,
            $duration,
            $outputPath
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        array_map('unlink', glob("{$tempDir}/*"));
        rmdir($tempDir);

        if ($returnCode !== 0) {
            throw new \RuntimeException("FFmpeg vertical slideshow failed (code {$returnCode}): " . implode("\n", array_slice($output, -10)));
        }

        return $outputPath;
    }

    /**
     * Create a thumbnail image from a video.
     */
    public function createThumbnail(string $videoPath, string $outputPath, int $second = 1): string
    {
        $cmd = sprintf(
            '%s -y -ss %d -i "%s" -vframes 1 -q:v 2 "%s" 2>&1',
            $this->ffmpegBin,
            $second,
            $videoPath,
            $outputPath
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException("FFmpeg thumbnail failed (code {$returnCode}): " . implode("\n", array_slice($output, -10)));
        }

        return $outputPath;
    }

    private function buildOverlayFilter(array $lines): string
    {
        if (empty($lines)) {
            return '';
        }

        $font = env('FFMPEG_FONT_PATH', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');
        if (!file_exists($font)) {
            return '';
        }
        $font = str_replace(':', '\\:', $font);
        $safeLines = array_values(array_filter(array_map('trim', $lines)));

        $filters = [];
        $positions = [120, 220, 320, 1760];
        foreach ($safeLines as $idx => $line) {
            $y = $positions[$idx] ?? (120 + ($idx * 90));
            $text = $this->escapeDrawText($line);
            $filters[] = "drawtext=fontfile='{$font}':text='{$text}':x=(w-text_w)/2:y={$y}:fontsize=56:fontcolor=white:box=1:boxcolor=black@0.45:boxborderw=18";
        }

        return ',' . implode(',', $filters);
    }

    private function escapeDrawText(string $text): string
    {
        $text = str_replace(['\\', ':', "'", '"', '%'], ['\\\\', '\\:', "\\'", '\\"', '\\%'], $text);
        return $text;
    }
}
