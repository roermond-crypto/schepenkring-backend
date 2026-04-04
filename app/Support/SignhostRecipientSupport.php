<?php

namespace App\Support;

use App\Models\SignRequest;
use App\Models\User;

final class SignhostRecipientSupport
{
    /**
     * @return array<int, mixed>
     */
    private static function recipients(SignRequest $signRequest): array
    {
        $recipients = $signRequest->metadata['recipients'] ?? [];

        return is_array($recipients) ? $recipients : [];
    }

    public static function clientCanAccess(SignRequest $signRequest, User $actor): bool
    {
        if ($signRequest->requested_by_user_id === $actor->id) {
            return true;
        }

        $actorEmail = strtolower(trim((string) $actor->email));

        foreach (self::recipients($signRequest) as $recipient) {
            if (! empty($recipient['user_id']) && (int) $recipient['user_id'] === $actor->id) {
                return true;
            }

            $recipientEmail = strtolower(trim((string) ($recipient['email'] ?? '')));
            if ($actorEmail !== '' && $recipientEmail !== '' && $recipientEmail === $actorEmail) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, int>
     */
    public static function recipientUserIds(SignRequest $signRequest): array
    {
        $ids = [];

        if ($signRequest->requested_by_user_id) {
            $ids[] = (int) $signRequest->requested_by_user_id;
        }

        foreach (self::recipients($signRequest) as $recipient) {
            if (! empty($recipient['user_id'])) {
                $ids[] = (int) $recipient['user_id'];
                continue;
            }

            $recipientEmail = strtolower(trim((string) ($recipient['email'] ?? '')));
            if ($recipientEmail === '') {
                continue;
            }

            $userId = User::query()
                ->whereRaw('LOWER(email) = ?', [$recipientEmail])
                ->value('id');

            if ($userId) {
                $ids[] = (int) $userId;
            }
        }

        return array_values(array_unique($ids));
    }

    public static function notificationUrl(SignRequest $signRequest): string
    {
        if (
            strcasecmp((string) $signRequest->entity_type, 'Yacht') === 0
            && $signRequest->entity_id
        ) {
            return "/dashboard/client/yachts/{$signRequest->entity_id}?step=6";
        }

        return "/dashboard/admin/contracts/{$signRequest->id}";
    }

    public static function clientSignUrl(SignRequest $signRequest, string $preferredRole = 'buyer'): ?string
    {
        $urls = $signRequest->metadata['sign_urls'] ?? [];

        if (is_array($urls)) {
            foreach ($urls as $entry) {
                if (
                    is_array($entry)
                    && ($entry['role'] ?? null) === $preferredRole
                    && ! empty($entry['url'])
                ) {
                    return (string) $entry['url'];
                }
            }

            foreach ($urls as $entry) {
                if (is_array($entry) && ! empty($entry['url'])) {
                    return (string) $entry['url'];
                }
            }
        }

        return $signRequest->sign_url ? (string) $signRequest->sign_url : null;
    }

    public static function hasSignedDocument(SignRequest $signRequest): bool
    {
        if (! empty($signRequest->metadata['signed_document_path'])) {
            return true;
        }

        return strtoupper((string) $signRequest->status) === 'SIGNED';
    }

    public static function normalizedSummaryStatus(?SignRequest $signRequest, ?string $entityStatus = null): string
    {
        if (! $signRequest) {
            return self::isEntityApprovedForContract($entityStatus)
                ? 'waiting_invite'
                : 'pending_review';
        }

        return match (strtoupper((string) $signRequest->status)) {
            'SIGNED' => 'signed',
            'SENT', 'VIEWED' => 'signing',
            'DECLINED', 'REJECTED', 'EXPIRED', 'FAILED', 'CANCELLED' => 'failed',
            'DRAFT', 'REQUESTED' => 'waiting_invite',
            default => self::isEntityApprovedForContract($entityStatus)
                ? 'waiting_invite'
                : 'pending_review',
        };
    }

    private static function isEntityApprovedForContract(?string $entityStatus): bool
    {
        return in_array(
            strtolower(trim((string) $entityStatus)),
            ['active', 'for sale', 'for_sale', 'sold', 'published'],
            true
        );
    }
}
