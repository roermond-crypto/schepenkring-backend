<?php

namespace App\Http\Middleware;

use App\Services\BidSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBidSession
{
    public function __construct(private BidSessionService $sessions)
    {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Bid-Token') ?? $request->bearerToken() ?? $request->input('bid_token');
        $session = $this->sessions->validateToken($token);

        if (! $session || ! $session->bidder) {
            return response()->json(['message' => 'Bid session token required.'], 401);
        }

        if (! $session->bidder->isVerified()) {
            return response()->json(['message' => 'Bidder is not verified.'], 403);
        }

        $request->attributes->set('bidder', $session->bidder);
        $request->attributes->set('bid_session', $session);
        $this->sessions->touch($session);

        return $next($request);
    }
}
