<?php

namespace App\Support;

class SellerOnboardingStatus
{
    public const CREATED = 'CREATED';
    public const PROFILE_COMPLETED = 'PROFILE_COMPLETED';
    public const PAYMENT_PENDING = 'PAYMENT_PENDING';
    public const PAYMENT_COMPLETED = 'PAYMENT_COMPLETED';
    public const CONTRACT_GENERATED = 'CONTRACT_GENERATED';
    public const SIGNHOST_VERIFICATION_STARTED = 'SIGNHOST_VERIFICATION_STARTED';
    public const IDIN_COMPLETED = 'IDIN_COMPLETED';
    public const IDEAL_COMPLETED = 'IDEAL_COMPLETED';
    public const KYC_PENDING = 'KYC_PENDING';
    public const KYC_COMPLETED = 'KYC_COMPLETED';
    public const CONTRACT_SIGNING_PENDING = 'CONTRACT_SIGNING_PENDING';
    public const CONTRACT_SIGNED = 'CONTRACT_SIGNED';
    public const MANUAL_REVIEW = 'MANUAL_REVIEW';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';

    public const TERMINAL = [
        self::APPROVED,
        self::REJECTED,
    ];
}
