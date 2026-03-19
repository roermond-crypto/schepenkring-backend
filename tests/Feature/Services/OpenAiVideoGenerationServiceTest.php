<?php

use App\Exceptions\RetryableOpenAiVideoException;
use App\Services\OpenAiVideoGenerationService;
use Illuminate\Support\Facades\Http;

test('openai video retrieve marks server errors as retryable', function () {
    Http::fake([
        'https://api.openai.com/v1/videos/video_retry' => Http::response([
            'error' => [
                'message' => 'temporary failure',
                'type' => 'server_error',
            ],
        ], 500),
    ]);

    config()->set('services.openai.key', 'test-key');

    expect(fn () => app(OpenAiVideoGenerationService::class)->retrieve('video_retry'))
        ->toThrow(RetryableOpenAiVideoException::class);
});
