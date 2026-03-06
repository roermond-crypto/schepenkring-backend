<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiImageRotationService
{
    private ?string $openAiKey;

    public function __construct()
    {
        $this->openAiKey = config('services.openai.key');
    }

    /**
     * Determine the required clockwise rotation angle (0, 90, 180, 270) to make the image upright.
     * Uses GPT-4o-mini for fast, cheap vision analysis.
     *
     * @param string $localPath Absolute path to the original image
     * @return int The rotation angle in degrees (default 0)
     */
    public function detectRotationAngle(string $localPath): int
    {
        if (empty($this->openAiKey)) {
            Log::info('[AiImageRotation] OpenAI key missing, skipping rotation detection.');
            return 0;
        }

        if (!file_exists($localPath)) {
            return 0;
        }

        try {
            $startTime = microtime(true);

            // Create a temporary small thumbnail to save tokens & latency
            $thumbPath = dirname($localPath) . '/rot_check_' . basename($localPath);
            
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick($localPath);
                $imagick->resizeImage(512, 512, \Imagick::FILTER_LANCZOS, 1, true);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(60);
                $imagick->writeImage($thumbPath);
                $imagick->clear();
                $imagick->destroy();
            } else {
                // GD Fallback
                $info = getimagesize($localPath);
                if ($info) {
                    $mime = $info['mime'];
                    $srcW = $info[0];
                    $srcH = $info[1];
                    
                    $srcImage = match ($mime) {
                        'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($localPath),
                        'image/png'               => @imagecreatefrompng($localPath),
                        'image/webp'              => @imagecreatefromwebp($localPath),
                        default                   => null,
                    };

                    if ($srcImage) {
                        $max = 512;
                        $dstW = $srcW;
                        $dstH = $srcH;
                        
                        if ($srcW > $max || $srcH > $max) {
                            if ($srcW > $srcH) {
                                $dstW = $max;
                                $dstH = intval($srcH * ($max / $srcW));
                            } else {
                                $dstH = $max;
                                $dstW = intval($srcW * ($max / $srcH));
                            }
                        }

                        $dstImage = imagecreatetruecolor($dstW, $dstH);
                        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
                        imagejpeg($dstImage, $thumbPath, 60);
                        imagedestroy($srcImage);
                        imagedestroy($dstImage);
                    } else {
                        throw new \RuntimeException("Could not process image with GD");
                    }
                } else {
                    throw new \RuntimeException("Could not get image size");
                }
            }

            $base64 = base64_encode(file_get_contents($thumbPath));
            @unlink($thumbPath);

            $payload = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a technical image analysis assistant. Your only job is to determine the correct rotation to make a photo upright based on obvious physical and visual cues (text, gravity, horizon, water).'
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => "Analyze this photo. Does it need to be rotated to be perfectly upright?

CRITICAL RULES:
1. IMAGES WITH READABLE TEXT/PANELS: If the image contains an electrical panel, gauge, document, or sign, the text MUST be readable left-to-right horizontally. Rotate it 90, 180, or 270 if the text is currently sideways or upside-down.
2. OUTDOOR/LANDSCAPE PHOTOS: Base your decision ONLY on the natural environment (water levels, sky, gravity, horizon).
3. CAPSIZED/SINKING BOATS: If the photo is outdoor and contains a wrecked or flipped boat *in the water*, DO NOT rotate the image just to make the boat stand up. Gravity/water must be at the bottom.
4. If the photo is ALREADY upright, or if you are unsure, you MUST return 0.

Select the clockwise rotation needed:
0 (Normal) - Already upright. (DEFAULT).
90 - Rotate 90 degrees clockwise.
180 - Rotate 180 degrees (upside down).
270 - Rotate 270 degrees clockwise.

Return EXACTLY ONE NUMBER: 0, 90, 180, or 270. Do not return any other text."
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:image/jpeg;base64,{$base64}",
                                    'detail' => 'auto'
                                ]
                            ]
                        ]
                    ]
                ],
                'temperature' => 0.0,
                'max_tokens' => 5,
            ];

            Log::info('[AiImageRotation] Asking OpenAI for rotation angle...');

            $response = Http::withToken($this->openAiKey)
                ->timeout(10)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->successful()) {
                Log::warning('[AiImageRotation] OpenAI API failed: ' . $response->body());
                return 0;
            }

            $data = $response->json();
            $content = trim($data['choices'][0]['message']['content'] ?? '0');
            
            $elapsed = round(microtime(true) - $startTime, 2);

            $angle = intval(preg_replace('/[^0-9]/', '', $content));

            if (!in_array($angle, [0, 90, 180, 270])) {
                if ($angle === 360) $angle = 0;
                else if ($angle === -90) $angle = 270;
                else $angle = 0;
            }

            Log::info("[AiImageRotation] OpenAI decided: {$angle} degrees (took {$elapsed}s)");

            return $angle;

        } catch (\Throwable $e) {
            Log::error("[AiImageRotation] Failed: " . $e->getMessage());
            return 0;
        }
    }
}
