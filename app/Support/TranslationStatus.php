<?php

namespace App\Support;

class TranslationStatus
{
    public const MISSING = 'MISSING';
    public const AI_DRAFT = 'AI_DRAFT';
    public const REVIEWED = 'REVIEWED';
    public const LEGAL_APPROVED = 'LEGAL_APPROVED';
    public const OUTDATED = 'OUTDATED';

    public static function all(): array
    {
        return [
            self::MISSING,
            self::AI_DRAFT,
            self::REVIEWED,
            self::LEGAL_APPROVED,
            self::OUTDATED,
        ];
    }

    public static function isApproved(?string $status): bool
    {
        return in_array($status, [self::REVIEWED, self::LEGAL_APPROVED], true);
    }
}
