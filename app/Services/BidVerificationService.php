<?php

namespace App\Services;

use App\Mail\BidVerificationMail;
use App\Models\Bidder;
use App\Support\AuthEmailSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BidVerificationService
{
    public function issue(Bidder $bidder, Request $request): string
    {
        $token = Str::random(64);

        $bidder->verification_token_hash = hash('sha256', $token);
        $bidder->verification_expires_at = now()->addMinutes((int) config('bidding.verification_ttl_minutes', 1440));
        $bidder->verification_sent_at = now();
        $bidder->verification_ip = $request->ip();
        $bidder->save();
        $locale = app(AuthEmailSupport::class)->resolveLocale(null, $request->header('Accept-Language'));

        try {
            Mail::to($bidder->email)->send(new BidVerificationMail($bidder, $token, $locale));
        } catch (\Throwable $e) {
            Log::error('Bid verification email failed', [
                'bidder_id' => $bidder->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $token;
    }
}
