<?php

namespace App\Support;

class BuyerVerificationStatus
{
    public const CREATED = 'CREATED';
    public const PROFILE_COMPLETED = 'PROFILE_COMPLETED';
    public const SIGNHOST_VERIFICATION_STARTED = 'SIGNHOST_VERIFICATION_STARTED';
    public const IDIN_COMPLETED = 'IDIN_COMPLETED';
    public const IDEAL_COMPLETED = 'IDEAL_COMPLETED';
    public const KYC_PENDING = 'KYC_PENDING';
    public const KYC_COMPLETED = 'KYC_COMPLETED';
    public const MANUAL_REVIEW = 'MANUAL_REVIEW';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';

    public const TERMINAL = [
        self::APPROVED,
        self::REJECTED,
    ];
}
