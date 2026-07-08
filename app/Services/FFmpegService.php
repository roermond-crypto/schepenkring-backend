<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class FFmpegService
{
    /**
     * Render a slideshow with xfade transitions and per-image Ken Burns motion.
     * Each image is first converted to a short clip with zoompan, then clips are
     * joined with xfade. Falls back to basic slideshow if < 2 images.
     *
     * @param array  $imagePaths       Ordered list of absolute image paths
     * @param string $outputPath       Destination .mp4
     * @param array  $durations        Per-image durations in seconds (same length as $imagePaths)
     * @param string $transition       xfade transition type: fade|fadeblack|dissolve|slideright|slideleft
     * @param float  $transitionDur    Overlap duration for xfade in seconds
     * @param string $resolution       WxH e.g. 1920:1080
     * @return string
     */
    public function renderSlideshowWithTransitions(
        array  $imagePaths,
        string $outputPath,
        array  $durations = [],
        string $transition = 'fade',
        float  $transitionDur = 0.8,
        string $resolution = '1920:1080'
    ): string {
        $validPaths = array_values(array_filter($imagePaths, 'file_exists'));

        if (count($validPaths) < 2) {
            // Fall back to basic slideshow
            $dur = $durations[0] ?? 3;
            return $this->renderGenericSlideshow($validPaths, $outputPath, (int) $dur, $resolution);
        }

        $uniqueId  = uniqid();
        $tempDisk  = 'local';
        $tempDir   = "ffmpeg_phase1_{$uniqueId}";
        Storage::disk($tempDisk)->makeDirectory($tempDir);
        $tempBase  = Storage::disk($tempDisk)->path($tempDir);

        try {
            // Step 1: Render each image into its own short clip with Ken Burns
            $clipPaths = [];
            foreach ($validPaths as $i => $imgPath) {
                $dur      = (float) ($durations[$i] ?? 3.0);
                $clipPath = "{$tempBase}/clip_{$i}.mp4";
                $this->renderKenBurnsClip($imgPath, $clipPath, $dur, $resolution, $i);
                $clipPaths[] = $clipPath;
            }

            // Step 2: Chain clips together with xfade
            $this->chainWithXfade($clipPaths, $durations, $outputPath, $transition, $transitionDur);
        } finally {
            Storage::disk($tempDisk)->deleteDirectory($tempDir);
        }

        return $outputPath;
    }

    /**
     * Render a single image into a video clip with Ken Burns (zoompan) effect.
     *
     * @param string $imagePath
     * @param string $outputPath
     * @param float  $duration     Clip length in seconds
     * @param string $resolution   WxH e.g. 1920:1080
     * @param int    $index        Used to alternate zoom direction
     * @return void
     */
    public function renderKenBurnsClip(
        string $imagePath,
        string $outputPath,
        float  $duration,
        string $resolution = '1920:1080',
        int    $index = 0
    ): void {
        $this->ensureParentDirectoryExists($outputPath);

        [$w, $h] = explode(':', $resolution);
        $fps    = 25;
        $frames = (int) ceil($duration * $fps);

        // Alternate zoom direction per scene for variety
        $zoompan = ($index % 2 === 0)
            ? "zoompan=z='min(zoom+0.0015,1.5)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d={$frames}:s={$w}x{$h}:fps={$fps}"
            : "zoompan=z='if(lte(zoom,1.0),1.5,max(1.001,zoom-0.0015))':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d={$frames}:s={$w}x{$h}:fps={$fps}";

        $scaleFilter = "scale=8000:-1,{$zoompan},scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2:black,setsar=1,format=yuv420p";

        $this->runFfmpeg([
            '-y',
            '-loop', '1',
            '-i', $imagePath,
            '-vf', $scaleFilter,
            '-t', (string) $duration,
            '-r', (string) $fps,
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            '-an',
            '-movflags', '+faststart',
            $outputPath,
        ]);
    }

    /**
     * Chain pre-rendered clips together using xfade transitions.
     *
     * @param array  $clipPaths
     * @param array  $durations       Per-clip durations in seconds
     * @param string $outputPath
     * @param string $transition
     * @param float  $transitionDur
     * @return void
     */
    public function chainWithXfade(        array  $clipPaths,
        array  $durations,
        string $outputPath,
        string $transition,
        float  $transitionDur
    ): void {
        $this->ensureParentDirectoryExists($outputPath);

        // Sanitize transition — crossfade is not valid in FFmpeg 4.x, map to dissolve
        $validTransitions = ['fade','dissolve','fadeblack','fadewhite','slideleft','slideright','slideup','slidedown','wipeleft','wiperight'];
        if (!in_array($transition, $validTransitions, true)) {
            $transition = 'fade';
        }

        if (count($clipPaths) === 1) {
            copy($clipPaths[0], $outputPath);
            return;
        }

        // Build ffmpeg inputs + filter_complex for chained xfade
        $inputs = [];
        foreach ($clipPaths as $clip) {
            $inputs[] = '-i';
            $inputs[] = $clip;
        }

        // Build xfade chain: [0][1]xfade=...[x01]; [x01][2]xfade=...[x02]; ...
        $filterParts = [];
        $offset      = 0.0;
        $lastLabel   = '[0:v]';

        for ($i = 1; $i < count($clipPaths); $i++) {
            $dur     = (float) ($durations[$i - 1] ?? 3.0);
            $offset  = round($offset + $dur - $transitionDur, 4);
            $outLabel = ($i === count($clipPaths) - 1) ? '[vout]' : "[x{$i}]";
            $filterParts[] = "{$lastLabel}[{$i}:v]xfade=transition={$transition}:duration={$transitionDur}:offset={$offset}{$outLabel}";
            $lastLabel = $outLabel;
        }

        $filterComplex = implode(';', $filterParts);

        $this->runFfmpeg(array_merge(
            ['-y'],
            $inputs,
            [
                '-filter_complex', $filterComplex,
                '-map', '[vout]',
                '-c:v', 'libx264',
                '-pix_fmt', 'yuv420p',
                '-movflags', '+faststart',
                $outputPath,
            ]
        ));
    }

    /**
     * Render a slideshow MP4 from a list of image paths.
     *
     * @param array $imagePaths
     * @param string $outputPath
     * @param int $secondsPerImage
     * @return string
     */
    public function renderSlideshow(array $imagePaths, string $outputPath, int $secondsPerImage = 3): string
    {
        return $this->renderGenericSlideshow($imagePaths, $outputPath, $secondsPerImage, '1920:1080');
    }

    /**
     * Render a vertical slideshow (1080x1920) with optional overlay text lines.
     *
     * @param array $imagePaths
     * @param string $outputPath
     * @param int $secondsPerImage
     * @param array $overlayLines
     * @return string
     */
    public function renderVerticalSlideshow(array $imagePaths, string $outputPath, int $secondsPerImage = 2, array $overlayLines = []): string
    {
        return $this->renderGenericSlideshow($imagePaths, $outputPath, $secondsPerImage, '1080:1920', $overlayLines);
    }

    /**
     * Internal helper to render a slideshow using the concat demuxer.
     * This is more reliable than pattern matching or multiple inputs.
     *
     * @param array $imagePaths
     * @param string $outputPath
     * @param int $secondsPerImage
     * @param string $resolution
     * @param array $overlayLines
     * @return string
     */
    protected function renderGenericSlideshow(array $imagePaths, string $outputPath, int $secondsPerImage, string $resolution, array $overlayLines = []): string
    {
        $uniqueId = uniqid();
        $tempDisk = 'local';
        $tempPath = "ffmpeg_tmp_{$uniqueId}";
        Storage::disk($tempDisk)->makeDirectory($tempPath);

        // 1. Create a concat file
        $concatFile = "{$tempPath}/inputs.txt";
        $contents = "";
        foreach ($imagePaths as $path) {
            if (file_exists($path)) {
                $contents .= "file '" . str_replace("'", "'\\''", $path) . "'\n";
                $contents .= "duration {$secondsPerImage}\n";
            }
        }
        // Re-add the last file without duration to close the stream correctly (FFmpeg requirement)
        if (!empty($imagePaths)) {
            $contents .= "file '" . str_replace("'", "'\\''", end($imagePaths)) . "'\n";
        }

        Storage::disk($tempDisk)->put($concatFile, $contents);
        try {
            $overlayFilterString = $this->buildPackageOverlayFilter($overlayLines);
            $scaleFilter = "scale={$resolution}:force_original_aspect_ratio=decrease,pad={$resolution}:(ow-iw)/2:(oh-ih)/2:black,setsar=1,format=yuv420p";

            if ($resolution === '1080:1920') {
                $scaleFilter .= ",gradfun=1";
            }

            $this->ensureParentDirectoryExists($outputPath);

            $this->runFfmpeg([
                '-y',
                '-f',
                'concat',
                '-safe',
                '0',
                '-i',
                Storage::disk($tempDisk)->path($concatFile),
                '-vf',
                $scaleFilter . $overlayFilterString,
                '-r',
                '30',
                '-c:v',
                'libx264',
                '-pix_fmt',
                'yuv420p',
                '-an',
                '-movflags',
                '+faststart',
                $outputPath,
            ]);
        } finally {
            Storage::disk($tempDisk)->deleteDirectory($tempPath);
        }

        return $outputPath;
    }

    /**
     * Add background music with fade-in and fade-out instead of hard cut.
     */
    public function addBackgroundMusicWithFade(
        string $videoPath,
        string $musicPath,
        string $outputPath,
        float  $fadeIn  = 1.5,
        float  $fadeOut = 2.0,
        float  $volume  = 0.35
    ): string {
        $this->ensureParentDirectoryExists($outputPath);

        $duration = $this->getDuration($videoPath);
        $fadeOutStart = max(0, $duration - $fadeOut);

        $this->runFfmpeg([
            '-y',
            '-i', $videoPath,
            '-stream_loop', '-1',
            '-i', $musicPath,
            '-filter_complex',
            "[1:a]volume={$volume},afade=t=in:st=0:d={$fadeIn},afade=t=out:st={$fadeOutStart}:d={$fadeOut}[looped]",
            '-map', '0:v',
            '-map', '[looped]',
            '-c:v', 'copy',
            '-shortest',
            $outputPath,
        ]);

        return $outputPath;
    }

    /**
     * Render a short teaser clip from the best (first N) images.
     * No intro/outro — faster pacing, strong first impression.
     */
    public function renderTeaserClip(
        array  $imagePaths,
        string $outputPath,
        int    $maxScenes   = 3,
        float  $sceneDur    = 2.5,
        string $resolution  = '1920:1080'
    ): string {
        $validPaths = array_slice(array_filter($imagePaths, 'file_exists'), 0, $maxScenes);
        $durations  = array_fill(0, count($validPaths), $sceneDur);

        return $this->renderSlideshowWithTransitions(
            array_values($validPaths),
            $outputPath,
            $durations,
            'fade',
            0.5,
            $resolution
        );
    }

    /**
     * Add background music to a video.
     *
     * @param string $videoPath
     * @param string $musicPath
     * @param string $outputPath
     * @return string
     */
    public function addBackgroundMusic(string $videoPath, string $musicPath, string $outputPath): string
    {
        $this->ensureParentDirectoryExists($outputPath);

        $this->runFfmpeg([
            '-y',
            '-i',
            $videoPath,
            '-i',
            $musicPath,
            '-map',
            '0:v',
            '-map',
            '1:a',
            '-c:v',
            'copy',
            '-shortest',
            $outputPath,
        ]);

        return $outputPath;
    }

    /**
     * Mix background music and voiceover with a video.
     *
     * @param string $videoPath
     * @param string $musicPath
     * @param string $voicePath
     * @param string $outputPath
     * @return string
     */
    public function mixAudioTracks(string $videoPath, string $musicPath, string $voicePath, string $outputPath): string
    {
        $this->ensureParentDirectoryExists($outputPath);

        $this->runFfmpeg([
            '-y',
            '-i',
            $videoPath,
            '-i',
            $musicPath,
            '-i',
            $voicePath,
            '-filter_complex',
            '[1:a]volume=0.3[music];[2:a]volume=1.0[voice];[music][voice]amix=inputs=2:duration=shortest[a]',
            '-map',
            '0:v',
            '-map',
            '[a]',
            '-c:v',
            'copy',
            '-shortest',
            $outputPath,
        ]);

        return $outputPath;
    }

    /**
     * Add a watermark logo to the bottom-right corner.
     *
     * @param string $videoPath
     * @param string $logoPath
     * @param string $outputPath
     * @return string
     */
    public function addWatermark(string $videoPath, string $logoPath, string $outputPath): string
    {
        $this->ensureParentDirectoryExists($outputPath);

        $this->runFfmpeg([
            '-y',
            '-i',
            $videoPath,
            '-i',
            $logoPath,
            '-filter_complex',
            'overlay=W-w-20:H-h-20',
            $outputPath,
        ]);

        return $outputPath;
    }

    /**
     * Get video duration in seconds.
     *
     * @param string $videoPath
     * @return int
     */
    public function getDuration(string $videoPath): int
    {
        $process = $this->runProcess(array_merge(
            [$this->ffprobeBinary()],
            ['-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $videoPath]
        ));

        return (int) round((float) trim($process->getOutput()));
    }

    /**
     * Normalize a location's own intro/outro asset (a still image or a short
     * video clip) into a clip matching the rest of the render's format, so it
     * can be spliced in via chainWithXfade() alongside the generated scenes.
     * Images get the same Ken Burns treatment as regular scene photos; videos
     * are re-encoded to the target resolution/fps and trimmed to $maxDuration.
     */
    public function prepareLocationMediaClip(
        string $mediaPath,
        string $outputPath,
        float $maxDuration,
        string $resolution = '1920:1080'
    ): ?string {
        if (! file_exists($mediaPath)) {
            return null;
        }

        $this->ensureParentDirectoryExists($outputPath);
        $isVideo = in_array(strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION)), ['mp4', 'mov', 'webm', 'm4v'], true);

        if (! $isVideo) {
            $this->renderKenBurnsClip($mediaPath, $outputPath, $maxDuration, $resolution);

            return $outputPath;
        }

        [$w, $h] = explode(':', $resolution);
        $sourceDuration = $this->getDuration($mediaPath);
        $clipDuration = $sourceDuration > 0 ? min($sourceDuration, $maxDuration) : $maxDuration;

        $this->runFfmpeg([
            '-y',
            '-i', $mediaPath,
            '-t', (string) $clipDuration,
            '-vf', "scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2:black,setsar=1,format=yuv420p",
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            '-an',
            $outputPath,
        ]);

        return $outputPath;
    }

    /**
     * Check if FFmpeg is installed and accessible.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $this->runProcess([$this->ffmpegBinary(), '-version']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a thumbnail image from a video.
     *
     * @param string $videoPath
     * @param string $outputPath
     * @param int $second
     * @return string
     */
    public function createThumbnail(string $videoPath, string $outputPath, int $second = 1): string
    {
        $this->ensureParentDirectoryExists($outputPath);

        $this->runFfmpeg([
            '-y',
            '-ss',
            (string) $second,
            '-i',
            $videoPath,
            '-frames:v',
            '1',
            $outputPath,
        ]);

        return $outputPath;
    }

    private function ffmpegBinary(): string
    {
        return config('laravel-ffmpeg.ffmpeg.binaries', 'ffmpeg');
    }

    private function ffprobeBinary(): string
    {
        return config('laravel-ffmpeg.ffprobe.binaries', 'ffprobe');
    }

    private function runFfmpeg(array $arguments): Process
    {
        return $this->runProcess(array_merge([$this->ffmpegBinary()], $arguments));
    }

    private function runProcess(array $command): Process
    {
        $process = new Process($command);
        $process->setTimeout((float) config('laravel-ffmpeg.timeout', 3600));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    private function ensureParentDirectoryExists(string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * Render a branded intro title card clip.
     * Black background with yacht name, subtitle, fade-in.
     */
    public function renderIntroClip(
        string $outputPath,
        string $title,
        string $subtitle = '',
        float  $duration = 3.0,
        string $resolution = '1920:1080'
    ): void {
        $this->ensureParentDirectoryExists($outputPath);
        [$w, $h] = explode(':', $resolution);
        $font   = $this->fontPath();
        $fps    = 25;
        $frames = (int) ceil($duration * $fps);

        $filters = "color=c=black:s={$w}x{$h}:r={$fps}:d={$duration}[base]";

        if ($font) {
            $safeTitle    = $this->escapeDrawText($title);
            $safeSubtitle = $this->escapeDrawText($subtitle);
            $titleY  = (int) ($h * 0.42);
            $subY    = (int) ($h * 0.56);
            $filters .= ";[base]drawtext=fontfile='{$font}':text='{$safeTitle}':x=(w-text_w)/2:y={$titleY}:fontsize=72:fontcolor=white:alpha='if(lt(t,0.8),t/0.8,1)'";
            if ($safeSubtitle !== '') {
                $filters .= ",drawtext=fontfile='{$font}':text='{$safeSubtitle}':x=(w-text_w)/2:y={$subY}:fontsize=42:fontcolor=white@0.85:alpha='if(lt(t,1.2),t/1.2,1)'";
            }
            $filters .= '[vout]';
            $map = '[vout]';
        } else {
            $filters .= '[vout]';
            $map = '[vout]';
        }

        $this->runFfmpeg([
            '-y',
            '-f', 'lavfi',
            '-i', "color=c=black:s={$w}x{$h}:r={$fps}:d={$duration}",
            '-vf', $font
                ? "drawtext=fontfile='{$font}':text='{$this->escapeDrawText($title)}':x=(w-text_w)/2:y=" . (int)($h*0.42) . ":fontsize=72:fontcolor=white,drawtext=fontfile='{$font}':text='{$this->escapeDrawText($subtitle)}':x=(w-text_w)/2:y=" . (int)($h*0.56) . ":fontsize=42:fontcolor=white@0.85,format=yuv420p"
                : 'format=yuv420p',
            '-t', (string) $duration,
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            '-an',
            '-movflags', '+faststart',
            $outputPath,
        ]);
    }

    /**
     * Render a branded outro/CTA clip.
     * Black background with CTA text and optional logo.
     */
    public function renderOutroClip(
        string  $outputPath,
        string  $ctaText,
        string  $subText = '',
        float   $duration = 4.0,
        string  $resolution = '1920:1080',
        ?string $logoPath = null
    ): void {
        $this->ensureParentDirectoryExists($outputPath);
        [$w, $h] = explode(':', $resolution);
        $font = $this->fontPath();
        $fps  = 25;

        $vfParts = [];
        if ($font) {
            $safeCta = $this->escapeDrawText($ctaText);
            $safeSub = $this->escapeDrawText($subText);
            $ctaY    = (int) ($h * 0.42);
            $subY    = (int) ($h * 0.56);
            $vfParts[] = "drawtext=fontfile='{$font}':text='{$safeCta}':x=(w-text_w)/2:y={$ctaY}:fontsize=64:fontcolor=white";
            if ($safeSub !== '') {
                $vfParts[] = "drawtext=fontfile='{$font}':text='{$safeSub}':x=(w-text_w)/2:y={$subY}:fontsize=38:fontcolor=white@0.8";
            }
        }
        $vfParts[] = 'format=yuv420p';
        $vf = implode(',', $vfParts);

        $this->runFfmpeg([
            '-y',
            '-f', 'lavfi',
            '-i', "color=c=black:s={$w}x{$h}:r={$fps}:d={$duration}",
            '-vf', $vf,
            '-t', (string) $duration,
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            '-an',
            '-movflags', '+faststart',
            $outputPath,
        ]);
    }

    /**
     * Add a single-line text overlay (headline) to an existing video clip.
     * Used for per-scene labels on the hero/feature scenes.
     */
    public function addSceneOverlay(
        string $inputPath,
        string $outputPath,
        string $headline,
        string $position = 'bottom' // 'bottom' or 'top'
    ): void {
        $this->ensureParentDirectoryExists($outputPath);
        $font = $this->fontPath();
        if (!$font) {
            copy($inputPath, $outputPath);
            return;
        }

        $safeText = $this->escapeDrawText($headline);
        $y        = $position === 'top' ? '60' : 'h-text_h-60';

        $this->runFfmpeg([
            '-y',
            '-i', $inputPath,
            '-vf', "drawtext=fontfile='{$font}':text='{$safeText}':x=(w-text_w)/2:y={$y}:fontsize=52:fontcolor=white:box=1:boxcolor=black@0.5:boxborderw=14,format=yuv420p",
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            '-an',
            '-movflags', '+faststart',
            $outputPath,
        ]);
    }

    /**
     * Build a full video: intro + scenes (Ken Burns + transitions) + outro.
     *
     * @param array  $imagePaths
     * @param string $outputPath
     * @param array  $durations        Per-scene durations
     * @param array  $sceneOverlays    Per-scene headline text (empty string = no overlay)
     * @param string $introTitle
     * @param string $introSubtitle
     * @param string $ctaText
     * @param string $ctaSubText
     * @param string $transition
     * @param float  $transitionDur
     * @param string $resolution
     * @return string
     */
    public function renderFullVideo(
        array  $imagePaths,
        string $outputPath,
        array  $durations    = [],
        array  $sceneOverlays = [],
        string $introTitle   = '',
        string $introSubtitle = '',
        string $ctaText      = '',
        string $ctaSubText   = '',
        string $transition   = 'fade',
        float  $transitionDur = 0.8,
        string $resolution   = '1920:1080',
        ?string $locationIntroMediaPath = null,
        ?string $locationOutroMediaPath = null
    ): string {
        $validPaths = array_values(array_filter($imagePaths, 'file_exists'));

        $uniqueId = uniqid();
        $tempDisk = 'local';
        $tempDir  = "ffmpeg_full_{$uniqueId}";
        Storage::disk($tempDisk)->makeDirectory($tempDir);
        $tempBase = Storage::disk($tempDisk)->path($tempDir);

        try {
            $allClips     = [];
            $allDurations = [];

            // Location's own opening clip/image, if the boat's location has one —
            // bookends the video before the generated title card so every
            // location's videos open with a distinct, recognizable identity.
            if ($locationIntroMediaPath) {
                $locIntroOut = "{$tempBase}/loc_intro.mp4";
                if ($this->prepareLocationMediaClip($locationIntroMediaPath, $locIntroOut, 3.0, $resolution)) {
                    $allClips[]     = $locIntroOut;
                    $allDurations[] = min($this->getDuration($locIntroOut) ?: 3.0, 3.0);
                }
            }

            // Intro
            if ($introTitle !== '') {
                $introPath = "{$tempBase}/intro.mp4";
                $this->renderIntroClip($introPath, $introTitle, $introSubtitle, 3.0, $resolution);
                $allClips[]     = $introPath;
                $allDurations[] = 3.0;
            }

            // Scene clips with Ken Burns
            foreach ($validPaths as $i => $imgPath) {
                $dur      = (float) ($durations[$i] ?? 3.0);
                $clipPath = "{$tempBase}/clip_{$i}.mp4";
                $this->renderKenBurnsClip($imgPath, $clipPath, $dur, $resolution, $i);

                // Add overlay if provided
                $headline = $sceneOverlays[$i] ?? '';
                if ($headline !== '') {
                    $overlayPath = "{$tempBase}/clip_{$i}_ov.mp4";
                    $this->addSceneOverlay($clipPath, $overlayPath, $headline);
                    $allClips[]     = $overlayPath;
                } else {
                    $allClips[]     = $clipPath;
                }
                $allDurations[] = $dur;
            }

            // Outro
            if ($ctaText !== '') {
                $outroPath = "{$tempBase}/outro.mp4";
                $this->renderOutroClip($outroPath, $ctaText, $ctaSubText, 4.0, $resolution);
                $allClips[]     = $outroPath;
                $allDurations[] = 4.0;
            }

            // Location's own closing clip/image, spliced in last.
            if ($locationOutroMediaPath) {
                $locOutroOut = "{$tempBase}/loc_outro.mp4";
                if ($this->prepareLocationMediaClip($locationOutroMediaPath, $locOutroOut, 3.0, $resolution)) {
                    $allClips[]     = $locOutroOut;
                    $allDurations[] = min($this->getDuration($locOutroOut) ?: 3.0, 3.0);
                }
            }

            if (count($allClips) < 2) {
                copy($allClips[0] ?? $validPaths[0], $outputPath);
                return $outputPath;
            }

            $this->chainWithXfade($allClips, $allDurations, $outputPath, $transition, $transitionDur);
        } finally {
            Storage::disk($tempDisk)->deleteDirectory($tempDir);
        }

        return $outputPath;
    }

    /**
     * Build the overlay filter for text lines.
     *
     * @param array $lines
     * @return string
     */
    private function buildPackageOverlayFilter(array $lines): string
    {
        if (empty($lines)) {
            return '';
        }

        $font = config('laravel-ffmpeg.font_path', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');
        if (!file_exists($font)) {
            // Log warning or use a simpler filter if font is missing
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

    /**
     * Escape text for drawtext filter.
     *
     * @param string $text
     * @return string
     */
    private function escapeDrawText(string $text): string
    {
        return str_replace(['\\', ':', "'", '"', '%'], ['\\\\', '\\:', "\\'", '\\"', '\\%'], $text);
    }

    private function fontPath(): ?string
    {
        $font = config('laravel-ffmpeg.font_path', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf');
        return file_exists($font) ? str_replace(':', '\\:', $font) : null;
    }
}
