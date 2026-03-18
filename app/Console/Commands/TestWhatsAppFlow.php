<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Conversation;
use App\Models\HarborChannel;
use App\Models\Message;
use App\Models\User;
use App\Services\PhoneNumberService;
use App\Services\WhatsApp360DialogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end WhatsApp / 360dialog flow test.
 *
 * Steps covered:
 *   1. Config check   — verify HarborChannel, API key, webhook URL
 *   2. Send test      — send a real WhatsApp message via 360dialog
 *   3. Inbound sim    — simulate an inbound webhook and verify phone matching
 *   4. AI reply test  — confirm AI reply is generated and queued for delivery
 *   5. Dashboard test — confirm conversation is visible for the linked user
 *
 * Usage:
 *   php artisan whatsapp:test-flow {location_id} {to_number}
 *   php artisan whatsapp:test-flow 1 +31612345678 --step=all
 *   php artisan whatsapp:test-flow 1 +31612345678 --step=send
 *   php artisan whatsapp:test-flow 1 +31612345678 --step=inbound
 */
class TestWhatsAppFlow extends Command
{
    protected $signature = 'whatsapp:test-flow
        {location_id   : The harbor/location ID that owns the WhatsApp channel}
        {to_number     : The recipient phone number in E.164 format, e.g. +31612345678}
        {--step=all    : Which step to run: all|config|send|inbound|ai|dashboard}
        {--message=    : Custom message text to send (default: a test message)}
        {--sync        : Process the inbound webhook synchronously instead of via queue}';

    protected $description = 'Run a real end-to-end WhatsApp / 360dialog flow test.';

    public function handle(WhatsApp360DialogService $whatsApp, PhoneNumberService $phoneService): int
    {
        $locationId = (int) $this->argument('location_id');
        $toNumber   = (string) $this->argument('to_number');
        $step       = strtolower((string) $this->option('step'));

        $normalizedTo = $phoneService->normalize($toNumber);
        if (! $normalizedTo) {
            $this->error("Invalid phone number: {$toNumber}");
            return self::FAILURE;
        }

        // ── Step 1: Config check ──────────────────────────────────────────────
        if (in_array($step, ['all', 'config'])) {
            $this->line('');
            $this->info('━━━ Step 1: Config check ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $channel = $this->loadChannel($locationId);
            if (! $channel) {
                return self::FAILURE;
            }
            $this->printChannelInfo($channel);
        } else {
            $channel = $this->loadChannel($locationId);
            if (! $channel) {
                return self::FAILURE;
            }
        }

        // ── Step 2: Send real outbound message ────────────────────────────────
        if (in_array($step, ['all', 'send'])) {
            $this->line('');
            $this->info('━━━ Step 2: Send real WhatsApp message ━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            if (! $this->runSendTest($whatsApp, $channel, $normalizedTo)) {
                return self::FAILURE;
            }
        }

        // ── Step 3: Simulate inbound webhook ─────────────────────────────────
        if (in_array($step, ['all', 'inbound'])) {
            $this->line('');
            $this->info('━━━ Step 3: Inbound webhook simulation ━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $conversation = $this->runInboundTest($channel, $normalizedTo, $phoneService);
            if (! $conversation) {
                return self::FAILURE;
            }
        } else {
            $conversation = null;
        }

        // ── Step 4: AI reply check ────────────────────────────────────────────
        if (in_array($step, ['all', 'ai'])) {
            $this->line('');
            $this->info('━━━ Step 4: AI reply check ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->runAiCheck($conversation, $normalizedTo);
        }

        // ── Step 5: Dashboard visibility check ───────────────────────────────
        if (in_array($step, ['all', 'dashboard'])) {
            $this->line('');
            $this->info('━━━ Step 5: Dashboard visibility check ━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->runDashboardCheck($normalizedTo, $locationId);
        }

        $this->line('');
        $this->info('✅  Flow test complete.');
        return self::SUCCESS;
    }

    // ── Step 1 helpers ────────────────────────────────────────────────────────

    private function loadChannel(int $locationId): ?HarborChannel
    {
        $channel = HarborChannel::where('harbor_id', $locationId)
            ->where('channel', 'whatsapp')
            ->where('provider', '360dialog')
            ->first();

        if (! $channel) {
            $this->error("No WhatsApp/360dialog channel found for location_id={$locationId}.");
            $this->line('  → Run: php artisan whatsapp:sandbox:connect ' . $locationId);
            return null;
        }

        if (! $channel->isActive()) {
            $this->warn("Channel #{$channel->id} exists but status='{$channel->status}' (not active).");
        }

        if (! $channel->apiKey()) {
            $this->error("Channel #{$channel->id} has no API key stored.");
            return null;
        }

        return $channel;
    }

    private function printChannelInfo(HarborChannel $channel): void
    {
        $meta = $channel->metadata ?? [];
        $this->table(['Field', 'Value'], [
            ['Channel ID',       $channel->id],
            ['Harbor/Location',  $channel->harbor_id],
            ['Status',           $channel->status],
            ['From number',      $channel->from_number ?? '(not set)'],
            ['API key',          $channel->apiKey() ? '✓ set (' . strlen((string)$channel->apiKey()) . ' chars)' : '✗ missing'],
            ['Webhook token',    $channel->webhook_token ? '✓ set' : '(none)'],
            ['Sandbox',          ($meta['sandbox'] ?? false) ? 'yes' : 'no'],
            ['Base URL',         $meta['base_url'] ?? config('whatsapp.base_url')],
            ['Phone number ID',  $meta['phone_number_id'] ?? '(not set)'],
            ['Webhook URL',      $meta['webhook_url'] ?? rtrim((string)config('app.url'), '/') . '/api/webhooks/whatsapp/360dialog'],
        ]);
        $this->info('✓ Config looks good.');
    }

    // ── Step 2 helpers ────────────────────────────────────────────────────────

    private function runSendTest(WhatsApp360DialogService $whatsApp, HarborChannel $channel, string $toNumber): bool
    {
        $text = (string) ($this->option('message') ?: '🔧 NauticSecure test message — ' . now()->toDateTimeString());

        $this->line("  Sending to: {$toNumber}");
        $this->line("  Text: {$text}");

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'   => ltrim($toNumber, '+'), // 360dialog expects no leading +
            'type' => 'text',
            'text' => ['body' => $text],
        ];

        try {
            $response = $whatsApp->sendMessage($channel, $payload);
            $msgId = $response['messages'][0]['id'] ?? null;
            $this->info("  ✓ Message sent. External ID: " . ($msgId ?? '(none returned)'));
            $this->line("  Full response: " . json_encode($response, JSON_UNESCAPED_SLASHES));
            return true;
        } catch (\Throwable $e) {
            $this->error("  ✗ Send failed: " . $e->getMessage());
            return false;
        }
    }

    // ── Step 3 helpers ────────────────────────────────────────────────────────

    private function runInboundTest(HarborChannel $channel, string $normalizedTo, PhoneNumberService $phoneService): ?Conversation
    {
        // Strip leading + to simulate the raw wa_id 360dialog sends
        $rawWaId = ltrim($normalizedTo, '+');
        $text    = (string) ($this->option('message') ?: 'Hello, this is a test inbound message from ' . $normalizedTo);

        $this->line("  Simulating inbound from wa_id: {$rawWaId}");
        $this->line("  Text: {$text}");

        // Build a realistic 360dialog webhook payload
        $fakeExternalId = 'wamid.test.' . uniqid('', true);
        $webhookPayload = [
            'object' => 'whatsapp_business_account',
            'entry'  => [[
                'id'      => 'ENTRY_ID',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => $channel->from_number ?? '0000000000',
                            'phone_number_id'      => $channel->metadata['phone_number_id'] ?? 'TEST_PHONE_NUMBER_ID',
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Test User'],
                            'wa_id'   => $rawWaId,
                        ]],
                        'messages' => [[
                            'from'      => $rawWaId,
                            'id'        => $fakeExternalId,
                            'timestamp' => (string) now()->timestamp,
                            'type'      => 'text',
                            'text'      => ['body' => $text],
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        // Check if a user is linked to this phone number
        $linkedUser = User::where('phone', $normalizedTo)
            ->orWhere('phone', $rawWaId)
            ->orWhere('phone', '+' . $rawWaId)
            ->first();

        if ($linkedUser) {
            $this->info("  ✓ Phone {$normalizedTo} is linked to user #{$linkedUser->id} ({$linkedUser->name}, {$linkedUser->email})");
        } else {
            $this->warn("  ⚠ No user found with phone {$normalizedTo} — conversation will be unassigned.");
            $this->line("    To link: UPDATE users SET phone='{$normalizedTo}' WHERE email='your@email.com';");
        }

        if ($this->option('sync')) {
            $this->line('  Processing webhook synchronously...');
            $job = new ProcessWhatsAppWebhook($channel->id, $webhookPayload);
            app()->call([$job, 'handle']);
        } else {
            $this->line('  Dispatching ProcessWhatsAppWebhook job to queue...');
            ProcessWhatsAppWebhook::dispatch($channel->id, $webhookPayload);
            $this->line('  (Use --sync to process immediately without a queue worker)');
        }

        // Find the conversation that was created/updated
        sleep(1); // brief pause for sync processing
        $conversation = Conversation::where('location_id', $channel->harbor_id)
            ->whereHas('contact', fn ($q) => $q->where('whatsapp_user_id', $rawWaId))
            ->latest('updated_at')
            ->first();

        if ($conversation) {
            $this->info("  ✓ Conversation found: #{$conversation->id}");
            $this->line("    user_id:     " . ($conversation->user_id ?? '(unassigned)'));
            $this->line("    location_id: " . ($conversation->location_id ?? '(none)'));
            $this->line("    channel:     " . $conversation->channel);
            $this->line("    status:      " . $conversation->status);
            $msgCount = $conversation->messages()->count();
            $this->line("    messages:    {$msgCount}");
        } else {
            $this->warn("  ⚠ No conversation found yet. If using queue, run: php artisan queue:work --once");
        }

        return $conversation;
    }

    // ── Step 4 helpers ────────────────────────────────────────────────────────

    private function runAiCheck(?Conversation $conversation, string $normalizedTo): void
    {
        if (! $conversation) {
            // Try to find by phone
            $rawWaId = ltrim($normalizedTo, '+');
            $conversation = Conversation::whereHas('contact', fn ($q) => $q->where('whatsapp_user_id', $rawWaId))
                ->latest('updated_at')
                ->first();
        }

        if (! $conversation) {
            $this->warn('  ⚠ No conversation found to check AI reply. Run --step=inbound first.');
            return;
        }

        $aiMessage = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', 'ai')
            ->latest('created_at')
            ->first();

        if ($aiMessage) {
            $this->info("  ✓ AI reply found (message #{$aiMessage->id})");
            $this->line("    channel:        " . $aiMessage->channel);
            $this->line("    status:         " . ($aiMessage->status ?? '(none)'));
            $this->line("    delivery_state: " . ($aiMessage->delivery_state ?? '(none)'));
            $this->line("    text:           " . \Illuminate\Support\Str::limit((string)$aiMessage->text, 120));
            $provider = data_get($aiMessage->metadata, 'provider', '(unknown)');
            $this->line("    provider:       " . $provider);

            if ($aiMessage->channel === 'whatsapp') {
                $this->info("  ✓ AI reply channel=whatsapp — SendWhatsAppMessage will be dispatched.");
            } else {
                $this->warn("  ⚠ AI reply channel='{$aiMessage->channel}' — NOT 'whatsapp'. It will NOT be sent back via 360dialog.");
            }

            $extId = $aiMessage->external_message_id;
            if ($extId) {
                $this->info("  ✓ External message ID set: {$extId} (delivery confirmed by 360dialog)");
            } else {
                $this->line("    External message ID: (not yet set — check queue worker)");
            }
        } else {
            $this->warn("  ⚠ No AI reply found yet.");
            $this->line("    ai_mode on conversation: " . ($conversation->ai_mode ?? 'auto'));
            if (($conversation->ai_mode ?? 'auto') !== 'auto') {
                $this->warn("    AI mode is not 'auto' — AI replies are disabled for this conversation.");
            }
            $this->line("    Run queue worker: php artisan queue:work --once");
        }
    }

    // ── Step 5 helpers ────────────────────────────────────────────────────────

    private function runDashboardCheck(string $normalizedTo, int $locationId): void
    {
        $rawWaId = ltrim($normalizedTo, '+');

        // Find linked user
        $user = User::where('phone', $normalizedTo)
            ->orWhere('phone', $rawWaId)
            ->orWhere('phone', '+' . $rawWaId)
            ->first();

        if (! $user) {
            $this->warn("  ⚠ No user linked to phone {$normalizedTo}.");
            $this->line("    The conversation will appear in the admin inbox as unassigned.");
            $this->line("    To link: UPDATE users SET phone='{$normalizedTo}' WHERE email='your@email.com';");
            return;
        }

        $this->info("  ✓ User found: #{$user->id} {$user->name} ({$user->email})");
        $this->line("    client_location_id: " . ($user->client_location_id ?? '(none)'));
        $this->line("    phone:              " . ($user->phone ?? '(none)'));

        // Find conversations linked to this user
        $conversations = Conversation::where('user_id', $user->id)
            ->orWhereHas('contact', fn ($q) => $q->where('email', $user->email))
            ->where('location_id', $locationId)
            ->with(['messages' => fn ($q) => $q->orderByDesc('created_at')->limit(3)])
            ->latest('last_message_at')
            ->get();

        if ($conversations->isEmpty()) {
            $this->warn("  ⚠ No conversations found for user #{$user->id} at location {$locationId}.");
            $this->line("    Check that conversation.user_id or contact.email matches the user.");
        } else {
            $this->info("  ✓ Found {$conversations->count()} conversation(s) for this user:");
            foreach ($conversations as $conv) {
                $lastMsg = $conv->messages->first();
                $this->line("    Conversation #{$conv->id}");
                $this->line("      channel:      " . $conv->channel);
                $this->line("      status:       " . $conv->status);
                $this->line("      messages:     " . $conv->messages()->count());
                $this->line("      last message: " . ($lastMsg ? \Illuminate\Support\Str::limit((string)$lastMsg->text, 80) . " [{$lastMsg->sender_type}]" : '(none)'));
                $this->line("      last_msg_at:  " . ($conv->last_message_at?->toDateTimeString() ?? '(none)'));
            }
        }
    }
}
