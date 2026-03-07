<?php

namespace App\Actions\Bids;

use App\Enums\RiskLevel;
use App\Models\Bidder;
use App\Repositories\BidderRepository;
use App\Services\ActionSecurity;
use App\Services\BidSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyBidderAction
{
    public function __construct(
        private BidderRepository $bidders,
        private BidSessionService $sessions,
        private ActionSecurity $security
    ) {
    }

    /**
     * @return array{bidder:Bidder, session:array}
     */
    public function execute(string $token, Request $request): array
    {
        $hash = hash('sha256', $token);
        $bidder = $this->bidders->findByVerificationTokenHash($hash);

        if (! $bidder) {
            throw ValidationException::withMessages([
                'token' => 'Invalid verification token.',
            ]);
        }

        if ($bidder->verification_expires_at && $bidder->verification_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => 'Verification token has expired.',
            ]);
        }

        $snapshotBefore = $bidder->toArray();

        if (! $bidder->verified_at) {
            $bidder->verified_at = now();
        }

        $bidder->verification_token_hash = null;
        $bidder->verification_expires_at = null;
        $bidder->save();

        $session = $this->sessions->issue($bidder, $request);

        $this->security->log('bidder.verified', RiskLevel::LOW, null, $bidder, [], [
            'snapshot_before' => $snapshotBefore,
            'snapshot_after' => $bidder->toArray(),
        ]);

        return [
            'bidder' => $bidder,
            'session' => $session,
        ];
    }
}
