<?php

namespace App\Actions\Bids;

use App\Enums\RiskLevel;
use App\Models\Bidder;
use App\Repositories\BidderRepository;
use App\Services\ActionSecurity;
use App\Services\BidSessionService;
use App\Services\BidVerificationService;
use Illuminate\Http\Request;

class RegisterBidderAction
{
    public function __construct(
        private BidderRepository $bidders,
        private BidVerificationService $verification,
        private BidSessionService $sessions,
        private ActionSecurity $security
    ) {
    }

    /**
     * @return array{bidder:Bidder, session:?array, verification_sent:bool}
     */
    public function execute(array $data, Request $request): array
    {
        $email = strtolower(trim($data['email']));
        $data['email'] = $email;

        $existing = $this->bidders->findByEmail($email);
        $snapshotBefore = $existing?->toArray();

        $bidder = $existing
            ? $this->bidders->update($existing, $data)
            : $this->bidders->create($data);

        $session = null;
        $verificationSent = false;

        if ($bidder->isVerified()) {
            $session = $this->sessions->issue($bidder, $request);
        } else {
            $this->verification->issue($bidder, $request);
            $verificationSent = true;
        }

        $this->security->log('bidder.registered', RiskLevel::LOW, null, $bidder, [
            'verified' => $bidder->isVerified(),
        ], [
            'snapshot_before' => $snapshotBefore,
            'snapshot_after' => $bidder->toArray(),
        ]);

        return [
            'bidder' => $bidder,
            'session' => $session,
            'verification_sent' => $verificationSent,
        ];
    }
}
