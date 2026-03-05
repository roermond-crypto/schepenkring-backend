<?php

namespace App\Services;

use App\Mail\OtpCodeMail;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OtpService
{
    public function createChallenge(User $user, string $purpose, Request $request, array $metadata = []): array
    {
        $ttl = (int) config('security.otp.ttl_minutes', 5);
        $code = (string) random_int(100000, 999999);

        $challenge = OtpChallenge::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
            'used_at' => null,
            'attempts' => 0,
            'sent_to' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_id' => $metadata['device_id'] ?? null,
            'metadata' => $metadata,
        ]);

        Mail::to($user->email)->send(new OtpCodeMail($code, $ttl, $purpose));

        return [
            'challenge' => $challenge,
            'ttl_minutes' => $ttl,
        ];
    }

    public function verifyChallenge(OtpChallenge $challenge, string $code): bool
    {
        if ($challenge->used_at || $challenge->expires_at->isPast()) {
            return false;
        }

        $maxAttempts = (int) config('security.otp.max_verify_attempts', 10);
        if ($challenge->attempts >= $maxAttempts) {
            return false;
        }

        $challenge->attempts = $challenge->attempts + 1;
        $challenge->save();

        if (!Hash::check($code, $challenge->code_hash)) {
            return false;
        }

        $challenge->used_at = now();
        $challenge->save();

        return true;
    }
}
