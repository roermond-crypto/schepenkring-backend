<?php

namespace App\Services;

use App\Models\Yacht;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;

class OpenAiVideoGenerationService
{
    private const BASE_URL = 'https://api.openai.com/v1/videos';

    public function providerName(): string
    {
        return 'openai_sora';
    }

    public function isConfigured(): bool
    {
        return (string) config('services.openai.key') !== '';
    }

    public function pollDelaySeconds(): int
    {
        return max(5, (int) config('video_automation.openai.poll_seconds', 20));
    }

    /**
     * @param  array<int, string>  $imagePaths
     * @return array<string, mixed>
     */
    public function submit(Yacht $yacht, array $imagePaths, string $workDir): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured for AI video generation.');
        }

        $payload = [
            'model' => $this->model(),
            'prompt' => $this->buildPrompt($yacht),
            'size' => $this->size(),
            'seconds' => $this->seconds(),
        ];

        $referencePath = $this->shouldUseReferenceImage()
            ? $this->prepareReferenceImage($imagePaths, $workDir)
            : null;

        return $this->submitRequest($payload, $referencePath, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(string $providerJobId): array
    {
        $response = Http::withToken((string) config('services.openai.key'))
            ->acceptJson()
            ->timeout($this->timeout())
            ->get(self::BASE_URL . '/' . rawurlencode($providerJobId));

        if ($response->failed()) {
            throw new \RuntimeException(sprintf(
                'OpenAI video status request failed [%d]: %s',
                $response->status(),
                $response->body()
            ));
        }

        return $response->json();
    }

    public function download(string $providerJobId, string $destinationPath): void
    {
        $response = Http::withToken((string) config('services.openai.key'))
            ->timeout(max($this->timeout(), 300))
            ->withOptions(['sink' => $destinationPath])
            ->get(self::BASE_URL . '/' . rawurlencode($providerJobId) . '/content');

        if ($response->failed()) {
            @unlink($destinationPath);

            throw new \RuntimeException(sprintf(
                'OpenAI video download failed [%d]: %s',
                $response->status(),
                $response->body()
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function status(array $payload): string
    {
        return strtolower(trim((string) ($payload['status'] ?? 'queued')));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function progress(array $payload): ?int
    {
        $progress = $payload['progress'] ?? null;

        if ($progress === null || $progress === '') {
            return $this->status($payload) === 'completed' ? 100 : null;
        }

        if (! is_numeric($progress)) {
            return null;
        }

        $normalized = (float) $progress;
        if ($normalized > 0 && $normalized <= 1) {
            $normalized *= 100;
        }

        return max(0, min(100, (int) round($normalized)));
    }

    public function isTerminalStatus(?string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'cancelled', 'expired'], true);
    }

    public function isFailureStatus(?string $status): bool
    {
        return in_array($status, ['failed', 'cancelled', 'expired'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function errorMessage(array $payload): string
    {
        $message = data_get($payload, 'last_error.message')
            ?? data_get($payload, 'error.message')
            ?? data_get($payload, 'failure_reason')
            ?? data_get($payload, 'message');

        $message = trim((string) $message);

        return $message !== ''
            ? $message
            : 'OpenAI video generation failed with status: ' . $this->status($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function durationSeconds(array $payload): ?int
    {
        $seconds = $payload['seconds'] ?? null;

        if ($seconds === null || ! is_numeric($seconds)) {
            return null;
        }

        return max(0, (int) $seconds);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function submitRequest(array $payload, ?string $referencePath, bool $allowReferenceFallback): array
    {
        $request = Http::withToken((string) config('services.openai.key'))
            ->acceptJson()
            ->timeout($this->timeout());

        if ($referencePath && is_file($referencePath)) {
            $request->attach(
                'input_reference',
                file_get_contents($referencePath),
                basename($referencePath),
                ['Content-Type' => mime_content_type($referencePath) ?: 'image/jpeg']
            );
        }

        $response = $request->post(self::BASE_URL, $payload);

        if ($response->failed()) {
            if ($referencePath && $allowReferenceFallback && $response->status() === 400) {
                Log::warning('[OpenAiVideoGeneration] Retrying without input_reference after OpenAI rejected the reference image.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->submitRequest($payload, null, false);
            }

            throw new \RuntimeException(sprintf(
                'OpenAI video creation failed [%d]: %s',
                $response->status(),
                $response->body()
            ));
        }

        return $response->json();
    }

    /**
     * @param  array<int, string>  $imagePaths
     */
    private function prepareReferenceImage(array $imagePaths, string $workDir): ?string
    {
        [$width, $height] = $this->dimensions();

        foreach ($imagePaths as $path) {
            if (! is_string($path) || trim($path) === '' || ! is_file($path)) {
                continue;
            }

            try {
                $encoded = Image::read($path)
                    ->cover($width, $height)
                    ->toJpeg(85);

                $destination = $workDir . '/openai-reference.jpg';
                file_put_contents($destination, (string) $encoded);

                return $destination;
            } catch (\Throwable $e) {
                Log::warning('[OpenAiVideoGeneration] Failed to prepare reference image.', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function buildPrompt(Yacht $yacht): string
    {
        $details = array_values(array_filter([
            $yacht->boat_name ? 'Boat name: ' . $yacht->boat_name : null,
            $yacht->manufacturer ? 'Manufacturer: ' . $yacht->manufacturer : null,
            $yacht->model ? 'Model: ' . $yacht->model : null,
            $yacht->year ? 'Year: ' . $yacht->year : null,
            $yacht->boat_type ? 'Type: ' . $yacht->boat_type : null,
            $yacht->location_city ? 'Location: ' . $yacht->location_city : null,
            $yacht->price !== null ? 'Price: EUR ' . number_format((float) $yacht->price, 0, '.', ',') : null,
        ]));

        $prompt = [
            'Create a premium photorealistic vertical yacht marketing video for social media.',
            'Use cinematic camera movement, natural daylight, subtle water reflections, and polished luxury-brokerage styling.',
            'Show only the yacht and marina environment. No people, no faces, no crew, no text overlays, no captions, no logos, no watermarks, and no split-screen layouts.',
            'If a reference image is provided, preserve the yacht silhouette, hull color, deck layout, major exterior details, and overall proportions.',
            'Sequence the clip with a strong hero opening shot, smooth side tracking, elegant close detail moments, and a clean aspirational closing frame.',
        ];

        if ($details !== []) {
            $prompt[] = 'Boat details: ' . implode('; ', $details) . '.';
        }

        return implode(' ', $prompt);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function dimensions(): array
    {
        if (! preg_match('/^(?<width>\d{3,4})x(?<height>\d{3,4})$/', $this->size(), $matches)) {
            throw new \RuntimeException('Invalid VIDEO_AUTOMATION_OPENAI_SIZE. Expected format WIDTHxHEIGHT.');
        }

        return [
            (int) $matches['width'],
            (int) $matches['height'],
        ];
    }

    private function shouldUseReferenceImage(): bool
    {
        return (bool) config('video_automation.openai.use_reference_image', true);
    }

    private function model(): string
    {
        return (string) config('video_automation.openai.model', 'sora-2');
    }

    private function size(): string
    {
        return (string) config('video_automation.openai.size', '720x1280');
    }

    private function seconds(): string
    {
        return (string) config('video_automation.openai.seconds', '8');
    }

    private function timeout(): int
    {
        return max(30, (int) config('video_automation.openai.timeout', 120));
    }
}
