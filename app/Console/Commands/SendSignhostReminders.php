<?php

namespace App\Console\Commands;

use App\Models\SignRequest;
use App\Models\User;
use App\Services\NotificationDispatchService;
use App\Support\SignhostRecipientSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendSignhostReminders extends Command
{
    protected $signature = 'app:signhost-reminders
        {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Send reminder notifications for contracts still unsigned after 2 or 5 days, and expired transaction alerts';

    public function handle(NotificationDispatchService $notifications): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info($isDryRun ? '[DRY RUN] Checking Signhost reminders…' : 'Checking Signhost reminders…');

        $sent2day  = 0;
        $sent5day  = 0;
        $expired   = 0;

        // ── 1. Contracts in SENT status for 2 days (first reminder) ──────────
        SignRequest::where('status', 'SENT')
            ->whereNotNull('signhost_transaction_id')
            ->whereNull('buyer_signed_at')
            ->where('created_at', '<=', now()->subDays(2))
            ->where('created_at', '>', now()->subDays(3))
            ->each(function (SignRequest $sr) use ($notifications, $isDryRun, &$sent2day) {
                $this->line("  [2-day] Sign request #{$sr->id} (yacht {$sr->entity_id})");
                if (!$isDryRun) {
                    $this->notifyRecipients($notifications, $sr,
                        'Herinnering: contract wacht op ondertekening',
                        'Het contract staat al 2 dagen open. Klik op de link om te ondertekenen.',
                        'reminder_2day'
                    );
                }
                $sent2day++;
            });

        // ── 2. Contracts in SENT status for 5 days (second reminder) ─────────
        SignRequest::where('status', 'SENT')
            ->whereNotNull('signhost_transaction_id')
            ->whereNull('buyer_signed_at')
            ->where('created_at', '<=', now()->subDays(5))
            ->where('created_at', '>', now()->subDays(6))
            ->each(function (SignRequest $sr) use ($notifications, $isDryRun, &$sent5day) {
                $this->line("  [5-day] Sign request #{$sr->id} (yacht {$sr->entity_id})");
                if (!$isDryRun) {
                    $this->notifyRecipients($notifications, $sr,
                        'Tweede herinnering: contract wacht op ondertekening',
                        'Het contract staat al 5 dagen open. Neem contact op als u hulp nodig heeft.',
                        'reminder_5day'
                    );
                }
                $sent5day++;
            });

        // ── 3. Contracts that just expired (status flipped to EXPIRED) ────────
        // We check sign requests with signhost_expires_at in the past 24 hours
        // that are still marked SENT (webhook may not have fired).
        SignRequest::where('status', 'SENT')
            ->whereNotNull('signhost_expires_at')
            ->where('signhost_expires_at', '<', now())
            ->where('signhost_expires_at', '>=', now()->subDay())
            ->each(function (SignRequest $sr) use ($notifications, $isDryRun, &$expired) {
                $this->line("  [expired] Sign request #{$sr->id} (yacht {$sr->entity_id})");
                if (!$isDryRun) {
                    // Mark as expired locally so the UI reflects it
                    $sr->update(['status' => 'EXPIRED']);

                    $this->notifyRecipients($notifications, $sr,
                        'Signhost transactie verlopen',
                        'De ondertekeningslink is verlopen. De beheerder moet een nieuwe transactie aanmaken.',
                        'expired'
                    );
                }
                $expired++;
            });

        $this->info("Done. 2-day: {$sent2day}, 5-day: {$sent5day}, expired: {$expired}.");

        return self::SUCCESS;
    }

    private function notifyRecipients(
        NotificationDispatchService $notifications,
        SignRequest $signRequest,
        string $title,
        string $message,
        string $event
    ): void {
        $recipientIds = SignhostRecipientSupport::recipientUserIds($signRequest);

        foreach ($recipientIds as $userId) {
            $user = User::find($userId);
            if (!$user) {
                continue;
            }

            try {
                $notifications->notifyUser(
                    $user,
                    'warning',
                    $title,
                    $message,
                    [
                        'event'          => $event,
                        'entity_type'    => $signRequest->entity_type,
                        'entity_id'      => $signRequest->entity_id,
                        'sign_request_id' => $signRequest->id,
                        'url'            => SignhostRecipientSupport::notificationUrl($signRequest),
                    ],
                    null,
                    true,
                    true,
                    $signRequest->location_id
                );
            } catch (\Throwable $e) {
                Log::warning("[SendSignhostReminders] Failed to notify user {$userId}: {$e->getMessage()}");
            }
        }
    }
}
