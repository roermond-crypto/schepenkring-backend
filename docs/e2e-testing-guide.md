# End-to-End Testing Guide — Telnyx + WhatsApp 360dialog

> **Purpose:** Prove that both integrations work in real, practical usage — not just "200 OK" API responses.
> Real phone. Real WhatsApp. Real dashboard result after login.

---

## Table of Contents

1. [Pre-flight Checklist](#1-pre-flight-checklist)
2. [WhatsApp / 360dialog — End-to-End Test](#2-whatsapp--360dialog--end-to-end-test)
3. [Telnyx Phone System — End-to-End Test](#3-telnyx-phone-system--end-to-end-test)
4. [Database Verification Queries](#4-database-verification-queries)
5. [Artisan Test Commands](#5-artisan-test-commands)
6. [Troubleshooting Reference](#6-troubleshooting-reference)
7. [Test Result Report Template](#7-test-result-report-template)

---

## 1. Pre-flight Checklist

Complete this before running any test. Every item must be ✅ before proceeding.

### 1.1 Environment Variables

Confirm the following are set in `.env` on the **server/staging environment**:

```dotenv
# ── App ──────────────────────────────────────────────────────────────────────
APP_URL=https://your-real-domain.com          # Must be publicly reachable (no localhost)

# ── WhatsApp / 360dialog ─────────────────────────────────────────────────────
WHATSAPP_360_BASE_URL=https://waba-v2.360dialog.io
WHATSAPP_360_MESSAGES_PATH=/v1/messages
WHATSAPP_360_WEBHOOK_PATH=/v1/configs/webhook
# (API key is stored per-channel in harbor_channels.api_key_encrypted — see §1.2)

# ── Telnyx ───────────────────────────────────────────────────────────────────
TELNYX_API_KEY=KEY0...
TELNYX_WEBHOOK_PUBLIC_KEY=           # Ed25519 public key from Telnyx portal
TELNYX_WEBHOOK_SECRET=               # HMAC secret (alternative to public key)
TELNYX_CONNECTION_ID=                # Your Telnyx TeXML/Call Control connection ID
TELNYX_APPLICATION_ID=               # Your Telnyx application ID

# ── Voice / Streaming ────────────────────────────────────────────────────────
VOICE_PROVIDER=telnyx
VOICE_GATEWAY_URL=                   # WebSocket URL for live call streaming (optional for basic test)
VOICE_DEFAULT_COUNTRY_DIAL_CODE=31   # e.g. 31 for Netherlands

# ── Queue ────────────────────────────────────────────────────────────────────
QUEUE_CONNECTION=database            # or redis — must NOT be 'sync' in production
```

### 1.2 Database: HarborChannel Records

Each location needs a `harbor_channels` row for each integration.

**WhatsApp channel:**
```sql
SELECT id, harbor_id, channel, provider, from_number, status,
       LENGTH(api_key_encrypted) AS api_key_len,
       webhook_token, metadata
FROM harbor_channels
WHERE channel = 'whatsapp' AND provider = '360dialog';
```

Expected result per location:
| Field | Expected value |
|---|---|
| `channel` | `whatsapp` |
| `provider` | `360dialog` |
| `status` | `active` |
| `from_number` | Your WhatsApp business number (E.164, e.g. `+31201234567`) |
| `api_key_encrypted` | Non-empty (the 360dialog API key) |
| `metadata->phone_number_id` | Set (from 360dialog dashboard) |

**Telnyx phone channel:**
```sql
SELECT id, harbor_id, channel, provider, from_number, status, metadata
FROM harbor_channels
WHERE channel = 'phone' AND provider = 'telnyx';
```

Expected result per location:
| Field | Expected value |
|---|---|
| `channel` | `phone` |
| `provider` | `telnyx` |
| `status` | `active` |
| `from_number` | The Telnyx DID for this location (E.164, e.g. `+31851234567`) |
| `metadata->connection_id` | Telnyx connection ID (or falls back to `TELNYX_CONNECTION_ID` env) |

> ⚠️ **Multi-location rule:** Each location must have its **own** `from_number`. Two locations must never share the same Telnyx number. Verify this now:
> ```sql
> SELECT from_number, COUNT(*) AS cnt
> FROM harbor_channels
> WHERE channel = 'phone' AND provider = 'telnyx'
> GROUP BY from_number HAVING cnt > 1;
> ```
> This query must return **zero rows**.

### 1.3 Queue Worker Running

```bash
# Check if a worker is running
ps aux | grep "queue:work"

# Start one if not running (keep this terminal open during tests)
php artisan queue:work --tries=3 --timeout=60
```

### 1.4 Webhook URLs Are Publicly Reachable

```bash
# WhatsApp webhook
curl -s -o /dev/null -w "%{http_code}" \
  -X POST https://your-domain.com/api/webhooks/whatsapp/360dialog \
  -H "Content-Type: application/json" \
  -d '{"test":true}'
# Expected: 401 (no valid token) — proves the endpoint is reachable

# Telnyx webhook
curl -s -o /dev/null -w "%{http_code}" \
  -X POST https://your-domain.com/api/webhooks/telnyx/voice \
  -H "Content-Type: application/json" \
  -d '{"test":true}'
# Expected: 400 or 401 — proves the endpoint is reachable
```

> If you get `000` or a connection error, the server is not publicly reachable. Fix this before continuing.

---

## 2. WhatsApp / 360dialog — End-to-End Test

### Overview of the full chain being tested

```
Real WhatsApp message sent by user
        ↓
360dialog receives it → fires webhook POST to /api/webhooks/whatsapp/360dialog
        ↓
WhatsApp360DialogWebhookController::handle()
  → resolves HarborChannel by webhook_token or phone_number_id
  → dispatches ProcessWhatsAppWebhook job
        ↓
ProcessWhatsAppWebhook::handle()
  → normalizes phone number to E.164
  → deduplicates by external_message_id
  → finds or creates Conversation (linked to harbor_id / location_id)
  → resolves User by phone → sets conversation.user_id + location_id
  → stores inbound Message (sender_type='visitor', channel='whatsapp')
  → calls ChatAiReplyService → generates AI reply
  → stores AI Message (sender_type='ai', channel='whatsapp')
  → dispatches SendWhatsAppMessage job
        ↓
SendWhatsAppMessage → calls WhatsApp360DialogService::sendMessage()
  → 360dialog delivers reply to user's WhatsApp
        ↓
Dashboard: GET /api/chat/conversations → shows thread with all messages
```

---

### Step 2.1 — Config Check (Artisan)

Run the built-in config check command:

```bash
php artisan whatsapp:test-flow {location_id} {your_whatsapp_number} --step=config
```

**Example:**
```bash
php artisan whatsapp:test-flow 1 +31612345678 --step=config
```

**Expected output:**
```
━━━ Step 1: Config check ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
┌─────────────────┬──────────────────────────────────────────────┐
│ Field           │ Value                                        │
├─────────────────┼──────────────────────────────────────────────┤
│ Channel ID      │ 3                                            │
│ Harbor/Location │ 1                                            │
│ Status          │ active                                       │
│ From number     │ +31201234567                                 │
│ API key         │ ✓ set (64 chars)                             │
│ Webhook token   │ ✓ set                                        │
│ Sandbox         │ no                                           │
│ Phone number ID │ 123456789012345                              │
└─────────────────┴──────────────────────────────────────────────┘
✓ Config looks good.
```

**If it fails:** See [§6.1 WhatsApp Config Errors](#61-whatsapp-config-errors).

---

### Step 2.2 — Send Real Outbound WhatsApp Message

```bash
php artisan whatsapp:test-flow {location_id} {real_whatsapp_number} --step=send
```

**Example:**
```bash
php artisan whatsapp:test-flow 1 +31612345678 --step=send \
  --message="NauticSecure test — please reply with 'hello'"
```

**Expected output:**
```
━━━ Step 2: Send real WhatsApp message ━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Sending to: +31612345678
  Text: NauticSecure test — please reply with 'hello'
  ✓ Message sent. External ID: wamid.HBgLMzE2...
```

**On the real phone:** The WhatsApp message must arrive within ~10 seconds.

✅ **Check:** Message received on real phone  
✅ **Check:** External message ID returned (not `(none returned)`)

---

### Step 2.3 — Inbound Webhook Test (Simulated)

This simulates a real inbound message from the phone number, processes it through the full job pipeline, and verifies user/location matching.

```bash
# With queue worker (recommended — mirrors production)
php artisan whatsapp:test-flow {location_id} {phone_number} --step=inbound

# Without queue worker (synchronous — useful for quick debugging)
php artisan whatsapp:test-flow {location_id} {phone_number} --step=inbound --sync
```

**Expected output:**
```
━━━ Step 3: Inbound webhook simulation ━━━━━━━━━━━━━━━━━━━━━━━━━━
  Simulating inbound from wa_id: 31612345678
  Text: Hello, this is a test inbound message from +31612345678
  ✓ Phone +31612345678 is linked to user #5 (Jan de Vries, jan@example.com)
  Dispatching ProcessWhatsAppWebhook job to queue...
  ✓ Conversation found: #42
    user_id:     5
    location_id: 1
    channel:     whatsapp
    status:      open
    messages:    2
```

**Critical checks:**
- `user_id` must be set (not `(unassigned)`) — proves phone → user matching works
- `location_id` must match the location you tested — proves location scoping works
- `messages` count must be ≥ 1

> ⚠️ **If `user_id` is `(unassigned)`:** The phone number in the test is not stored on any `users` record. Fix:
> ```sql
> UPDATE users SET phone = '+31612345678' WHERE email = 'jan@example.com';
> ```
> Then re-run the inbound step.

---

### Step 2.4 — Real Inbound Webhook Test (From Real Phone)

Now test with a **real WhatsApp message** from the phone:

1. On the real phone, open WhatsApp and send a message to the business number
2. Watch the server logs in real time:
   ```bash
   tail -f storage/logs/laravel.log | grep -E "(WhatsApp|360dialog|conversation|user_id)"
   ```
3. Verify the webhook was received:
   ```bash
   # Check the queue jobs table
   php artisan tinker --execute="echo \App\Models\Message::where('channel','whatsapp')->where('sender_type','visitor')->latest()->first()?->text;"
   ```

**Expected log entries (in order):**
```
[INFO] WhatsApp conversation linked to user {"conversation_id":42,"user_id":5,"phone":"+31612345678"}
```

✅ **Check:** Webhook hit the endpoint (check access log or Laravel log)  
✅ **Check:** `WhatsApp conversation linked to user` log line appears  
✅ **Check:** Inbound message stored in DB with `sender_type='visitor'`

---

### Step 2.5 — AI Reply Check

```bash
php artisan whatsapp:test-flow {location_id} {phone_number} --step=ai
```

**Expected output:**
```
━━━ Step 4: AI reply check ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✓ AI reply found (message #87)
    channel:        whatsapp
    status:         sent
    delivery_state: sent
    text:           Goedemiddag! Hoe kan ik u helpen?
    provider:       openai
  ✓ AI reply channel=whatsapp — SendWhatsAppMessage will be dispatched.
  ✓ External message ID set: wamid.HBgL... (delivery confirmed by 360dialog)
```

**Critical checks:**
- `channel` must be `whatsapp` — if it's `web` or anything else, the reply will NOT be sent back via WhatsApp
- `status` should be `sent` or `delivered`
- `external_message_id` should be set (proves 360dialog accepted the outbound message)

**On the real phone:** The AI reply must arrive on WhatsApp within ~30 seconds.

✅ **Check:** AI reply message exists in DB with `channel='whatsapp'`  
✅ **Check:** AI reply received on real phone  
✅ **Check:** `external_message_id` is set on the AI reply message

---

### Step 2.6 — Dashboard Visibility Check

```bash
php artisan whatsapp:test-flow {location_id} {phone_number} --step=dashboard
```

**Expected output:**
```
━━━ Step 5: Dashboard visibility check ━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✓ User found: #5 Jan de Vries (jan@example.com)
    client_location_id: 1
    phone:              +31612345678
  ✓ Found 1 conversation(s) for this user:
    Conversation #42
      channel:      whatsapp
      status:       open
      messages:     3
      last message: Goedemiddag! Hoe kan ik u helpen? [ai]
      last_msg_at:  2026-03-18 11:00:00
```

**Then verify in the actual dashboard UI:**

1. Log in to the dashboard as the linked user (or as admin)
2. Navigate to **Chat / Inbox**
3. Confirm the conversation appears with:
   - Correct contact name
   - Both the inbound message (visitor) and the AI reply visible
   - Correct location/harbor shown
   - Timestamps correct

✅ **Check:** Conversation visible in dashboard after login  
✅ **Check:** Both inbound and outbound messages shown  
✅ **Check:** Correct location shown  
✅ **Check:** No missing relation errors in browser console or Laravel log

---

### Step 2.7 — Full Flow in One Command

Run all steps together:

```bash
php artisan whatsapp:test-flow {location_id} {phone_number} --step=all --sync
```

---

### Step 2.8 — Delivery Status Webhook Test

After the AI reply is sent, 360dialog fires status webhooks (`sent`, `delivered`, `read`). Verify these are processed:

```sql
-- Check delivery states on recent WhatsApp messages
SELECT id, external_message_id, sender_type, status, delivery_state,
       delivered_at, read_at, created_at
FROM messages
WHERE channel = 'whatsapp'
ORDER BY created_at DESC
LIMIT 10;
```

Expected progression: `queued` → `sent` → `delivered` → `read`

✅ **Check:** `delivery_state` updates from `sent` → `delivered` after phone receives it  
✅ **Check:** `read_at` is set after the user opens the message

---

## 3. Telnyx Phone System — End-to-End Test

### Overview of the full chain being tested

```
External phone calls the Telnyx DID assigned to a location
        ↓
Telnyx fires webhook POST to /api/webhooks/telnyx/voice
  event_type: call.initiated / call.incoming
        ↓
TelnyxVoiceWebhookController::handle()
  → verifies Ed25519 signature (or HMAC)
  → stores WebhookEvent
  → dispatches ProcessTelnyxWebhook job
        ↓
ProcessTelnyxWebhook → PhoneCallService::handleCallInitiated()
  → normalizes from/to numbers
  → resolveHarborChannel(): matches to_number → HarborChannel → location_id
  → resolveConversation(): finds/creates Contact + Conversation
  → stores CallSession (direction='inbound', harbor_id=location_id)
  → calls TelnyxService::answerCall()
        ↓
Call answered → call.answered webhook
  → PhoneCallService::handleCallAnswered()
  → starts audio streaming (if VOICE_GATEWAY_URL is set)
        ↓
Call ends → call.hangup webhook
  → PhoneCallService::handleCallEnded()
  → calculates duration, cost
  → creates 'call_summary' system message in conversation
  → creates 'call_transcript' message (if transcript available)
        ↓
Dashboard: conversation shows call summary with duration
```

---

### Step 3.1 — Verify Number-to-Location Mapping

Before making any calls, confirm each location's Telnyx number is correctly mapped:

```bash
php artisan tinker
```

```php
// List all Telnyx phone channels and their locations
\App\Models\HarborChannel::where('channel', 'phone')
    ->where('provider', 'telnyx')
    ->with('location')
    ->get()
    ->each(function ($ch) {
        echo "Location #{$ch->harbor_id} ({$ch->location?->name}): {$ch->from_number} [status: {$ch->status}]\n";
        echo "  connection_id: " . ($ch->metadata['connection_id'] ?? 'uses env default') . "\n";
    });
```

**Expected:** Each location has exactly one active Telnyx channel with a unique `from_number`.

✅ **Check:** Each location has its own unique Telnyx number  
✅ **Check:** No two locations share the same number  
✅ **Check:** All channels have `status = active`

---

### Step 3.2 — Inbound Call Test

**Setup:**
- Have a real external phone ready (not the Telnyx number itself)
- Know which location's number you are calling
- Have the server logs open: `tail -f storage/logs/laravel.log`

**Test procedure:**

1. **Call** the Telnyx DID for Location #1 from the external phone
2. The call should be **answered automatically** (Telnyx answerCall is called)
3. Wait 10–15 seconds, then **hang up**

**Verify in logs (in order):**
```
[INFO] (no specific log for call.initiated — check WebhookEvent table)
```

```bash
# Check WebhookEvent was stored
php artisan tinker --execute="
\App\Models\WebhookEvent::where('provider','telnyx')
    ->latest()->limit(3)->get(['id','event_key','processed_at','created_at'])
    ->each(fn(\$e) => print_r(\$e->toArray()));
"
```

```bash
# Check CallSession was created
php artisan tinker --execute="
\App\Models\CallSession::latest()->limit(3)->get([
    'id','direction','status','from_number','to_number',
    'harbor_id','conversation_id','outcome','duration_seconds'
])->each(fn(\$s) => print_r(\$s->toArray()));
"
```

**Expected CallSession:**
```
direction:       inbound
status:          ended
from_number:     +31612345678   (caller's number)
to_number:       +31851234567   (your Telnyx DID)
harbor_id:       1              (correct location!)
conversation_id: 55             (linked conversation)
outcome:         completed
duration_seconds: 15
```

✅ **Check:** `WebhookEvent` row created with `processed_at` set  
✅ **Check:** `CallSession` created with correct `harbor_id` (location match)  
✅ **Check:** `from_number` = caller's real phone number  
✅ **Check:** `to_number` = the Telnyx DID that was called  
✅ **Check:** `conversation_id` is set (not null)

---

### Step 3.3 — Verify Location Isolation (Multi-location)

If you have multiple locations, call **Location #2's** number and verify it does NOT appear in Location #1's data:

```bash
php artisan tinker --execute="
// After calling Location 2's number:
\$session = \App\Models\CallSession::latest()->first();
echo 'harbor_id: ' . \$session->harbor_id . PHP_EOL;
echo 'to_number: ' . \$session->to_number . PHP_EOL;
// harbor_id must be 2, NOT 1
"
```

✅ **Check:** Calling Location #2's number creates a CallSession with `harbor_id = 2`  
✅ **Check:** Location #1's conversations are NOT affected

---

### Step 3.4 — Dashboard Visibility (Inbound Call)

After the inbound call ends:

```bash
php artisan tinker --execute="
\$session = \App\Models\CallSession::with('conversation.messages')->latest()->first();
if (\$session->conversation) {
    echo 'Conversation #' . \$session->conversation->id . PHP_EOL;
    echo 'location_id: ' . \$session->conversation->location_id . PHP_EOL;
    \$session->conversation->messages->each(fn(\$m) => 
        print_r(['type' => \$m->message_type, 'text' => \$m->text, 'sender' => \$m->sender_type])
    );
}
"
```

**Expected:** A `call_summary` system message like `"Call ended (0:15)"` exists in the conversation.

**Then verify in the dashboard UI:**
1. Log in as admin or the location's staff user
2. Navigate to **Chat / Inbox** for Location #1
3. Find the conversation for the caller's phone number
4. Confirm the call summary is visible with correct duration

✅ **Check:** Call appears in correct location's dashboard  
✅ **Check:** Call summary message shows correct duration  
✅ **Check:** Caller's phone number is stored correctly  
✅ **Check:** Conversation is scoped to the correct location (not visible in other locations)

---

### Step 3.5 — Outbound Call Test

**From the dashboard:**
1. Open a conversation that has a contact with a phone number
2. Click the **Call** button (this creates a `message_type='call'` Message and dispatches `InitiateOutboundCall`)
3. The external phone should ring within ~5 seconds

**Verify in logs:**
```bash
tail -f storage/logs/laravel.log | grep -E "(telnyx|outbound|call_control)"
```

**Expected log sequence:**
```
[INFO] (Telnyx API call to POST /calls)
```

```bash
# Verify the outbound CallSession
php artisan tinker --execute="
\$session = \App\Models\CallSession::where('direction','outbound')->latest()->first();
echo 'direction:    ' . \$session->direction . PHP_EOL;
echo 'status:       ' . \$session->status . PHP_EOL;
echo 'from_number:  ' . \$session->from_number . PHP_EOL;  // Must be the location's Telnyx DID
echo 'to_number:    ' . \$session->to_number . PHP_EOL;    // Must be the contact's phone
echo 'harbor_id:    ' . \$session->harbor_id . PHP_EOL;
echo 'call_control_id: ' . \$session->call_control_id . PHP_EOL;
"
```

**Critical checks:**
- `from_number` must be the **location's Telnyx DID** (not a random number, not the caller's number)
- `call_control_id` must be set (proves Telnyx accepted the call)
- `status` should progress from `ringing` → `answered` → `ended`

✅ **Check:** External phone rings  
✅ **Check:** `from_number` = correct location's Telnyx DID (caller ID is correct)  
✅ **Check:** `call_control_id` is set  
✅ **Check:** Call log saved in DB after call ends  
✅ **Check:** Call summary appears in dashboard conversation

---

### Step 3.6 — Outbound Call: Multi-location Caller ID Verification

This is critical: when Location #1 makes an outbound call, the caller ID shown on the recipient's phone must be Location #1's Telnyx number — NOT Location #2's number.

**Test:**
1. From Location #1's dashboard, initiate an outbound call
2. On the receiving phone, check the caller ID displayed
3. Confirm it matches Location #1's Telnyx DID exactly

```bash
# Confirm from_number in DB matches Location 1's channel
php artisan tinker --execute="
\$session = \App\Models\CallSession::where('direction','outbound')->latest()->first();
\$channel = \App\Models\HarborChannel::where('harbor_id', \$session->harbor_id)
    ->where('channel','phone')->where('provider','telnyx')->first();
echo 'Session from_number: ' . \$session->from_number . PHP_EOL;
echo 'Channel from_number: ' . \$channel?->from_number . PHP_EOL;
echo 'Match: ' . (\$session->from_number === \$channel?->from_number ? 'YES ✓' : 'NO ✗') . PHP_EOL;
"
```

✅ **Check:** Caller ID on receiving phone = Location's Telnyx DID  
✅ **Check:** DB `from_number` matches the location's `harbor_channels.from_number`

---

### Step 3.7 — Webhook Signature Verification

Telnyx signs all webhooks with Ed25519. Verify the signature check is working:

```bash
# Test with a fake/unsigned payload — must be rejected
curl -s -w "\nHTTP %{http_code}\n" \
  -X POST https://your-domain.com/api/webhooks/telnyx/voice \
  -H "Content-Type: application/json" \
  -d '{"data":{"event_type":"call.initiated","payload":{"call_control_id":"fake"}}}'
# Expected: HTTP 401 (invalid signature)
```

> **Note:** If `TELNYX_WEBHOOK_PUBLIC_KEY` and `TELNYX_WEBHOOK_SECRET` are both empty in `.env`, the signature check is bypassed (returns `true`). This is acceptable for local dev but **must be configured in production**.

✅ **Check:** Unsigned requests return 401  
✅ **Check:** `TELNYX_WEBHOOK_PUBLIC_KEY` is set in production `.env`

---

## 4. Database Verification Queries

Run these after completing the tests to confirm data integrity.

### 4.1 WhatsApp Messages

```sql
-- Recent WhatsApp messages with conversation and user info
SELECT
    m.id,
    m.conversation_id,
    m.sender_type,
    m.channel,
    m.message_type,
    LEFT(m.text, 80) AS text_preview,
    m.status,
    m.delivery_state,
    m.external_message_id,
    c.user_id,
    c.location_id,
    c.channel_origin,
    u.email AS user_email
FROM messages m
JOIN conversations c ON c.id = m.conversation_id
LEFT JOIN users u ON u.id = c.user_id
WHERE m.channel = 'whatsapp'
ORDER BY m.created_at DESC
LIMIT 20;
```

**Expected:** Rows with `sender_type` alternating between `visitor` and `ai`, both with `channel='whatsapp'`.

### 4.2 Phone Calls

```sql
-- Recent call sessions with location and conversation info
SELECT
    cs.id,
    cs.direction,
    cs.status,
    cs.outcome,
    cs.from_number,
    cs.to_number,
    cs.harbor_id,
    cs.conversation_id,
    cs.duration_seconds,
    cs.cost_eur,
    cs.call_control_id,
    cs.started_at,
    cs.ended_at
FROM call_sessions cs
ORDER BY cs.created_at DESC
LIMIT 10;
```

### 4.3 Channel Identity (Thread Linking)

```sql
-- Verify WhatsApp thread keys are correctly formed
SELECT
    ci.id,
    ci.conversation_id,
    ci.type,
    ci.external_thread_id,
    ci.external_user_id,
    ci.metadata
FROM channel_identities ci
WHERE ci.type IN ('whatsapp', 'phone')
ORDER BY ci.created_at DESC
LIMIT 10;
```

**Expected WhatsApp thread key format:** `whatsapp:{harbor_id}:{wa_id}` (e.g. `whatsapp:1:31612345678`)  
**Expected Phone thread key format:** `phone:{harbor_id}:{phone}` (e.g. `phone:1:+31612345678`)

### 4.4 No Orphaned Records

```sql
-- Conversations without location_id (should be 0 for WhatsApp/phone)
SELECT COUNT(*) AS orphaned_conversations
FROM conversations
WHERE location_id IS NULL
AND channel_origin IN ('whatsapp', 'phone');

-- Messages without conversation
SELECT COUNT(*) AS orphaned_messages
FROM messages
WHERE conversation_id IS NULL;

-- CallSessions without harbor_id
SELECT COUNT(*) AS unlinked_calls
FROM call_sessions
WHERE harbor_id IS NULL
AND direction = 'inbound';
```

All three queries must return **0**.

---

## 5. Artisan Test Commands

### 5.1 WhatsApp Flow Test (Built-in)

```bash
# Full flow (all steps)
php artisan whatsapp:test-flow {location_id} {phone} --step=all --sync

# Individual steps
php artisan whatsapp:test-flow 1 +31612345678 --step=config
php artisan whatsapp:test-flow 1 +31612345678 --step=send
php artisan whatsapp:test-flow 1 +31612345678 --step=inbound --sync
php artisan whatsapp:test-flow 1 +31612345678 --step=ai
php artisan whatsapp:test-flow 1 +31612345678 --step=dashboard

# Custom message
php artisan whatsapp:test-flow 1 +31612345678 --step=send \
  --message="Test bericht van NauticSecure"
```

### 5.2 Manual Webhook Simulation (curl)

**Simulate a WhatsApp inbound message:**
```bash
# Replace WEBHOOK_TOKEN, PHONE_NUMBER_ID, FROM_NUMBER, HARBOR_FROM_NUMBER
curl -s -X POST https://your-domain.com/api/webhooks/whatsapp/360dialog \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Token: YOUR_WEBHOOK_TOKEN" \
  -d '{
    "object": "whatsapp_business_account",
    "entry": [{
      "id": "ENTRY_ID",
      "changes": [{
        "value": {
          "messaging_product": "whatsapp",
          "metadata": {
            "display_phone_number": "HARBOR_FROM_NUMBER",
            "phone_number_id": "PHONE_NUMBER_ID"
          },
          "contacts": [{
            "profile": {"name": "Test Caller"},
            "wa_id": "31612345678"
          }],
          "messages": [{
            "from": "31612345678",
            "id": "wamid.test.unique123",
            "timestamp": "'$(date +%s)'",
            "type": "text",
            "text": {"body": "Hallo, ik wil meer informatie"}
          }]
        },
        "field": "messages"
      }]
    }]
  }'
```

**Expected response:** `{"message":"ok"}`

### 5.3 Queue Monitoring

```bash
# Process one job at a time (useful for debugging)
php artisan queue:work --once --tries=1

# Watch queue in real time
php artisan queue:work --tries=3 --timeout=60

# Check failed jobs
php artisan queue:failed

# Retry a failed job
php artisan queue:retry {job_id}

# Retry all failed jobs
php artisan queue:retry all
```

### 5.4 Tinker Verification Snippets

```bash
php artisan tinker
```

```php
// Check latest WhatsApp conversation
$conv = \App\Models\Conversation::where('channel_origin','whatsapp')
    ->with(['messages','contact','user'])
    ->latest()->first();
echo "Conversation #{$conv->id}\n";
echo "user_id: {$conv->user_id}\n";
echo "location_id: {$conv->location_id}\n";
echo "Messages: " . $conv->messages->count() . "\n";
$conv->messages->each(fn($m) => print("{$m->sender_type}: {$m->text}\n"));

// Check latest call session
$call = \App\Models\CallSession::with('conversation')->latest()->first();
echo "Call {$call->id}: {$call->direction} {$call->from_number} → {$call->to_number}\n";
echo "harbor_id: {$call->harbor_id}, outcome: {$call->outcome}\n";

// Check phone number normalization
$svc = app(\App\Services\PhoneNumberService::class);
echo $svc->normalize('0031612345678') . "\n";  // → +31612345678
echo $svc->normalize('31612345678') . "\n";    // → +31612345678 (with VOICE_DEFAULT_COUNTRY_DIAL_CODE)
echo $svc->normalize('+31612345678') . "\n";   // → +31612345678
```

---

## 6. Troubleshooting Reference

### 6.1 WhatsApp Config Errors

| Error | Cause | Fix |
|---|---|---|
| `No WhatsApp/360dialog channel found for location_id=X` | No `harbor_channels` row | Insert a row with `channel='whatsapp'`, `provider='360dialog'`, `status='active'` |
| `Channel #X has no API key stored` | `api_key_encrypted` is null | Update the channel with the 360dialog API key |
| `WhatsApp send failed` (status 401) | Wrong API key | Verify the API key in the 360dialog dashboard |
| `WhatsApp send failed` (status 400) | Wrong payload format | Check `to` field — must be without leading `+` |
| Webhook returns 401 | Token mismatch | Ensure `webhook_token` in DB matches the `X-Webhook-Token` header 360dialog sends |
| Webhook returns 401 (no token) | `phone_number_id` not in metadata | Set `metadata->phone_number_id` on the `harbor_channels` row |

### 6.2 WhatsApp Flow Errors

| Symptom | Cause | Fix |
|---|---|---|
| `user_id` is null on conversation | Phone number not on any `users` record | `UPDATE users SET phone='+31...' WHERE email='...'` |
| `location_id` is null on conversation | `user.client_location_id` is null | Set `client_location_id` on the user record |
| AI reply not sent to WhatsApp | AI message `channel` ≠ `whatsapp` | Check `ChatAiReplyService` — the `whatsapp_channel` hint must be passed |
| AI reply not generated | `ai_mode` ≠ `auto` on conversation | Set `ai_mode='auto'` on the conversation |
| Duplicate messages | `external_message_id` not unique | Check for duplicate webhook deliveries from 360dialog |
| Opt-out not working | Phrase not in config | Add phrase to `whatsapp.opt_out_phrases` in `config/whatsapp.php` |

### 6.3 Telnyx Config Errors

| Error | Cause | Fix |
|---|---|---|
| Webhook returns 401 (invalid signature) | Wrong `TELNYX_WEBHOOK_PUBLIC_KEY` | Copy the Ed25519 public key from Telnyx portal → Webhooks |
| Webhook returns 400 (stale webhook) | Server clock out of sync | Sync server time: `ntpdate -u pool.ntp.org` |
| `harbor_channel_not_found` in CallSession | `to_number` not in `harbor_channels.from_number` | Add/fix the Telnyx channel row for this number |
| `missing_number` in CallSession | Telnyx payload format unexpected | Check `PhoneCallService::extractNumber()` against actual Telnyx payload |
| `telnyx_initiate_failed` | API key wrong or connection_id missing | Verify `TELNYX_API_KEY` and `TELNYX_CONNECTION_ID` |
| Call not answered | `answerCall` failed | Check Telnyx application webhook URL points to your server |

### 6.4 Telnyx Flow Errors

| Symptom | Cause | Fix |
|---|---|---|
| `harbor_id` is null on CallSession | `resolveHarborChannel` failed | Verify `from_number` in `harbor_channels` matches the Telnyx DID exactly (E.164) |
| `conversation_id` is null on CallSession | Contact not found/created | Check `PhoneCallService::resolveConversation()` — contact creation may have failed |
| Wrong location on call | Two locations share a number | Each location must have a unique Telnyx DID |
| Outbound call uses wrong caller ID | `resolveHarborChannelForHarbor` returned wrong channel | Verify only one active phone channel per `harbor_id` |
| Call summary not in dashboard | `conversation_id` null on CallSession | Fix the inbound call flow first (see above) |
| Signature bypass in production | Both `TELNYX_WEBHOOK_PUBLIC_KEY` and `TELNYX_WEBHOOK_SECRET` are empty | Set at least one in production `.env` |

### 6.5 Queue Issues

```bash
# Jobs stuck in queue?
php artisan tinker --execute="echo \DB::table('jobs')->count() . ' pending jobs';"

# Failed jobs?
php artisan tinker --execute="echo \DB::table('failed_jobs')->count() . ' failed jobs';"

# See failed job details
php artisan queue:failed

# Clear failed jobs (after fixing the root cause)
php artisan queue:flush
```

---

## 7. Test Result Report Template

After completing all tests, fill in this report and send to the team.

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NauticSecure — E2E Integration Test Report
Date: _______________
Tester: _______________
Environment: staging / production
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

WHATSAPP / 360DIALOG
Location tested: #___  (name: _______________)
Test phone number: _______________

[ ] Outbound send — message received on real phone
    External message ID: _______________

[ ] Inbound webhook — webhook hit the endpoint
    HTTP response: ___  (expected: 200)

[ ] Phone normalization — number stored correctly in DB
    Raw wa_id: _______________
    Normalized: _______________
    Stored in messages.metadata: _______________

[ ] User match — conversation.user_id set correctly
    user_id: ___  email: _______________

[ ] Location match — conversation.location_id set correctly
    location_id: ___  (expected: ___)

[ ] AI reply — generated and sent back via WhatsApp
    AI message channel: ___  (must be 'whatsapp')
    Received on real phone: yes / no
    External message ID: _______________

[ ] Dashboard visibility — conversation visible after login
    Conversation ID: ___
    Messages shown: ___
    Inbound visible: yes / no
    AI reply visible: yes / no
    No console errors: yes / no

WhatsApp notes / issues found:
_______________________________________________________________
_______________________________________________________________

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

TELNYX PHONE SYSTEM
Location #1 tested: #___  Telnyx DID: _______________
Location #2 tested: #___  Telnyx DID: _______________  (if applicable)

INBOUND CALL
[ ] Inbound call — webhook received by backend
    WebhookEvent ID: ___  processed_at: _______________

[ ] Location match — called number → correct location_id
    Called number: _______________
    Expected location_id: ___  Actual harbor_id: ___
    Match: yes / no

[ ] Caller number stored correctly
    Caller number (real phone): _______________
    Stored in call_sessions.from_number: _______________
    Match: yes / no

[ ] Call appears in correct dashboard location
    Conversation ID: ___  location_id: ___
    Call summary message: yes / no
    Duration shown: ___

[ ] Multi-location isolation (if tested)
    Location #2 call did NOT appear in Location #1: yes / no

OUTBOUND CALL
[ ] Outbound call initiated from dashboard
    External phone rang: yes / no

[ ] Correct caller ID used
    Expected caller ID (Location DID): _______________
    Shown on receiving phone: _______________
    Match: yes / no

[ ] Call log saved correctly
    CallSession ID: ___
    direction: outbound
    from_number: _______________
    outcome: _______________

[ ] Dashboard log visible after call
    Call summary in conversation: yes / no

Telnyx notes / issues found:
_______________________________________________________________
_______________________________________________________________

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

OVERALL RESULT

WhatsApp / 360dialog:  ✅ PASS  /  ❌ FAIL  /  ⚠️ PARTIAL
Telnyx:                ✅ PASS  /  ❌ FAIL  /  ⚠️ PARTIAL

Blocking issues (must fix before go-live):
1. _______________________________________________________________
2. _______________________________________________________________

Non-blocking issues (can fix after go-live):
1. _______________________________________________________________
2. _______________________________________________________________
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## Quick Reference Card

| What to test | Command / Action |
|---|---|
| WhatsApp config check | `php artisan whatsapp:test-flow 1 +31612345678 --step=config` |
| Send real WhatsApp | `php artisan whatsapp:test-flow 1 +31612345678 --step=send` |
| Simulate inbound (sync) | `php artisan whatsapp:test-flow 1 +31612345678 --step=inbound --sync` |
| Check AI reply | `php artisan whatsapp:test-flow 1 +31612345678 --step=ai` |
| Check dashboard | `php artisan whatsapp:test-flow 1 +31612345678 --step=dashboard` |
| Full WhatsApp flow | `php artisan whatsapp:test-flow 1 +31612345678 --step=all --sync` |
| Watch logs | `tail -f storage/logs/laravel.log` |
| Process queue | `php artisan queue:work --once` |
| Check failed jobs | `php artisan queue:failed` |
| Check call sessions | `php artisan tinker` → `CallSession::latest()->first()` |
| Check webhook events | `php artisan tinker` → `WebhookEvent::where('provider','telnyx')->latest()->first()` |
| Verify number mapping | `HarborChannel::where('channel','phone')->get(['harbor_id','from_number','status'])` |
