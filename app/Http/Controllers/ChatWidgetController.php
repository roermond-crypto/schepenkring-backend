<?php

namespace App\Http\Controllers;

use App\Services\ChatAbuseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ChatWidgetController extends Controller
{
    public function init(Request $request, ChatAbuseService $abuse)
    {
        $request->validate([
            'visitor_id' => 'nullable|string|max:64',
            'harbor_id' => 'nullable|integer',
        ]);

        $abuse->ensureNotBlocked(null, null, null, $request->ip());
        $abuse->rateLimit($request, $request->input('visitor_id'), null);

        $visitorId = $request->input('visitor_id') ?: (string) Str::uuid();
        $sessionId = (string) Str::uuid();
        $harborId = (int) ($request->input('harbor_id') ?: 1);

        $payload = [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'harbor_id' => $harborId,
            'issued_at' => now()->timestamp,
        ];

        $sessionJwt = Crypt::encryptString(json_encode($payload));

        return response()->json([
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'session_jwt' => $sessionJwt,
        ]);
    }
}
