<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailTrackingController extends Controller
{
    private const TRANSPARENT_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7';

    public function pixel(string $token, EmailTrackingService $tracking): Response
    {
        $tracking->recordOpen($token);

        return response(base64_decode(self::TRANSPARENT_GIF_BASE64), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function click(string $token, Request $request, EmailTrackingService $tracking)
    {
        $url = (string) $request->query('url', '');

        // Only ever redirect to our own frontend — an open-redirect via a
        // tracking link is a classic phishing vector, so a URL that doesn't
        // resolve to the configured frontend host is refused outright
        // rather than silently redirected elsewhere.
        $frontendHost = parse_url((string) config('app.frontend_url', ''), PHP_URL_HOST);
        $targetHost = parse_url($url, PHP_URL_HOST);

        if ($url === '' || ! $targetHost || ! $frontendHost || $targetHost !== $frontendHost) {
            abort(400, 'Invalid redirect target');
        }

        $tracking->recordClick($token, $url);

        return redirect()->away($url);
    }
}
