<?php

namespace App\Services;

use App\Models\Bidder;
use App\Models\BidSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BidSessionService
{
    public function issue(Bidder $bidder, Request $request): array
    {
        if (! $bidder->isVerified()) {
            throw ValidationException::withMessages([
                'bidder' => 'Bidder must be verified before issuing a session.',
            ]);
        }

        $token = Str::random(64);
        $hash = hash('sha256', $token);

        $session = BidSession::create([
            'bidder_id' => $bidder->id,
            'token_hash' => $hash,
            'expires_at' => now()->addDays((int) config('bidding.session_ttl_days', 365)),
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return [
            'token' => $token,
            'session' => $session,
        ];
    }

    public function validateToken(?string $token): ?BidSession
    {
        if (! $token) {
            return null;
        }

        $hash = hash('sha256', $token);
        $session = BidSession::with('bidder')->where('token_hash', $hash)->first();
        if (! $session) {
            return null;
        }

        if ($session->isExpired()) {
            $session->delete();
            return null;
        }

        return $session;
    }

    public function touch(BidSession $session): void
    {
        $session->update([
            'last_used_at' => now(),
        ]);
    }
}
