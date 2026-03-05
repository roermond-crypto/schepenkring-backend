<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MailgunService
{
    public function send(string $to, string $subject, string $html, ?string $text = null): array
    {
        $domain = env('MAILGUN_DOMAIN');
        $apiKey = env('MAILGUN_API_KEY');
        $from = env('MAILGUN_FROM_ADDRESS');

        if (!$domain || !$apiKey || !$from) {
            return [
                'ok' => false,
                'error' => 'Mailgun not configured',
            ];
        }

        $response = Http::withBasicAuth('api', $apiKey)
            ->asForm()
            ->post("https://api.mailgun.net/v3/{$domain}/messages", [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'html' => $html,
                'text' => $text ?: strip_tags($html),
            ]);

        if ($response->failed()) {
            return [
                'ok' => false,
                'error' => $response->body(),
            ];
        }

        return [
            'ok' => true,
            'id' => $response->json('id'),
            'message' => $response->json('message'),
        ];
    }
}
