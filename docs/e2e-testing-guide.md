# End-to-End Testing Guide — Telnyx + WhatsApp 360dialog

> **Purpose:** Prove both integrations work in real usage — not just "200 OK".  
> This guide covers every step a developer must execute, what to check, and how to report results.

---

## 1. WhatsApp / 360dialog — Full Flow Test

### What must be proven

| Step | What to verify |
|------|---------------|
| Send real WhatsApp message | Message leaves the platform and arrives on a real phone |
| Receive inbound reply webhook | Backend receives the webhook, processes it, no 500 errors |
| Phone number normalization | Sender number is stored in E.164 format (`+31612345678`) |
| User match | `phone_number → user_id` lookup succeeds |
| Location match | `user_id → location_id` is resolved correctly |
| AI reply | AI generates a response and sends it back on WhatsApp |
| DB storage | Both inbound and outbound messages are stored in `messages` table |
| Dashboard visibility | Conversation appears in the correct location's dashboard after login |

---

### Step-by-step test procedure

#### Step 1 — Verify 360dialog credentials are set

```bash
php artisan tinker --execute="echo config('whatsapp.api_key') ? 'KEY OK' : 'MISSING';"
php artisan tinker --execute="echo config('whatsapp.base_url') ? 'URL OK' : 'MISSING';"
```

Both must return `OK`. If not, check `.env` for `WHATSAPP_API_KEY` and `WHATSAPP_BASE_URL`.

---

#### Step 2 — Send a real outbound WhatsApp message

Use the artisan command or API endpoint to send a test message to a real phone number:

```bash
php artisan tinker
```

```php
$service = app(\App\Services\WhatsApp360DialogService::class);
$result = $service->sendMessage('+31612345678', 'Test message from NauticSecure');
dump($result);
```

**Expected:** HTTP 201 from 360dialog, message arrives on the real phone within 30 seconds.

**Check ✅ / ❌:** Message received on phone?

---

#### Step 3 — Trigger an inbound webhook

Reply to the message from the real phone. Then check the backend received it:

```bash
tail -f storage/logs/laravel.log | grep -i whatsapp
```

Or check the `webhook_events` table:

```bash
php artisan tinker --execute="dump(\App\Models\WebhookEvent::latest()->first());"
```

**Expected:** A new `webhook_event` row with `source = 'whatsapp'` and the correct payload.

**Check ✅ / ❌:** Webhook received and logged?

---

#### Step 4 — Verify phone number normalization

```bash
php artisan tinker
```

```php
$service = app(\App\Services\PhoneNumberService::class);
// Test various formats
dump($service->normalize('0612345678'));       // should → +31612345678
dump($service->normalize('+31612345678'));     // should → +31612345678
dump($service->normalize('31612345678'));      // should → +31612345678
```

**Check ✅ / ❌:** All formats normalize to E.164?

---

#### Step 5 — Verify user match from phone number

```bash
php artisan tinker
```

```php
// Replace with the phone number you used in Step 2
$phone = '+31612345678';
$user = \App\Models\User::where('phone', $phone)->first();
dump($user?->id, $user?->name, $user?->type);
```

**Expected:** A user record is found with the correct `id`.

**Check ✅ / ❌:** User found from phone number?

---

#### Step 6 — Verify location match from user

```bash
php artisan tinker
```

```php
$user = \App\Models\User::where('phone', '+31612345678')->first();
dump($user?->location_id);           // via getLocationIdAttribute()
dump($user?->client_location_id);    // for client users
dump($user?->resolvedLocationId());  // canonical method
```

**Expected:** A non-null `location_id` is returned.

**Check ✅ / ❌:** Location resolved from user?

---

#### Step 7 — Verify AI reply was generated and sent

Check the `messages` table for the AI outbound reply:

```bash
php artisan tinker
```

```php
$messages = \App\Models\Message::latest()->take(5)->get(['id', 'direction', 'channel', 'body', 'created_at']);
dump($messages->toArray());
```

**Expected:** Two rows — one `inbound` (the user's reply) and one `outbound` (the AI response), both with `channel = 'whatsapp'`.

Also check the job was dispatched and processed:

```bash
php artisan tinker --execute="dump(\App\Models\WebhookEvent::where('source','whatsapp')->latest()->value('processed_at'));"
```

**Check ✅ / ❌:** AI reply sent back on WhatsApp?  
**Check ✅ / ❌:** Both messages stored in DB?

---

#### Step 8 — Verify dashboard visibility

1. Log in to the dashboard as a staff user for the correct location
2. Navigate to the conversations/chat section
3. Find the conversation thread for the test phone number

**Expected:** The full thread (inbound + AI outbound) is visible, linked to the correct location.

**Check ✅ / ❌:** Conversation visible in dashboard?  
**Check ✅ / ❌:** Correct location shown?  
**Check ✅ / ❌:** No missing relation errors in logs?

---

### WhatsApp result checklist

```
[ ] sent                — real WhatsApp message arrived on phone
[ ] inbound webhook     — backend received and logged the reply
[ ] user match          — phone number → user_id resolved
[ ] location match      — user_id → location_id resolved
[ ] AI reply            — AI response sent back on WhatsApp
[ ] dashboard visibility — conversation visible in correct location dashboard
```

---

## 2. Telnyx Phone System — Full Flow Test

### What must be proven

| Step | What to verify |
|------|---------------|
| Number-to-location mapping | Each location has its own Telnyx number configured |
| Inbound call | Calling the number triggers the backend webhook |
| Location match from called number | `called_number → location_id` resolves correctly |
| Caller number stored | Caller's phone number is saved in the call log |
| Call appears in dashboard | Call log visible in the correct location's dashboard |
| Outbound call from dashboard | Initiating a call uses the correct location's number as caller ID |
| Outbound caller ID | The outgoing call shows the location's Telnyx number |
| Call log saved | Both inbound and outbound calls are stored in `call_sessions` |

---

### Step-by-step test procedure

#### Step 1 — Verify Telnyx credentials

```bash
php artisan tinker --execute="echo config('services.telnyx.api_key') ? 'KEY OK' : 'MISSING';"
```

**Check ✅ / ❌:** Telnyx API key present?

---

#### Step 2 — Verify number-to-location mapping

```bash
php artisan tinker
```

```php
// List all locations and their Telnyx numbers
\App\Models\Location::whereNotNull('telnyx_number')
    ->get(['id', 'name', 'telnyx_number'])
    ->each(fn($l) => dump($l->toArray()));
```

**Expected:** Each location that should have a Telnyx number has one configured.

**Check ✅ / ❌:** All locations have their own unique Telnyx number?

---

#### Step 3 — Make a real inbound call

1. Call the Telnyx number assigned to **Location A** from a real external phone
2. Immediately check the backend logs:

```bash
tail -f storage/logs/laravel.log | grep -i telnyx
```

3. Check the webhook was received:

```bash
php artisan tinker --execute="dump(\App\Models\WebhookEvent::where('source','telnyx')->latest()->first());"
```

**Check ✅ / ❌:** Inbound webhook received?

---

#### Step 4 — Verify called number maps to correct location

```bash
php artisan tinker
```

```php
$telnyxNumber = '+31201234567'; // the number you called
$location = \App\Models\Location::where('telnyx_number', $telnyxNumber)->first();
dump($location?->id, $location?->name);
```

**Expected:** The correct location is found.

**Check ✅ / ❌:** Called number → correct location_id?

---

#### Step 5 — Verify caller number is stored

```bash
php artisan tinker
```

```php
$call = \App\Models\CallSession::latest()->first();
dump($call?->caller_number, $call?->location_id, $call?->direction, $call?->created_at);
```

**Expected:** `caller_number` is the phone you called from (E.164 format), `location_id` matches Location A, `direction = 'inbound'`.

**Check ✅ / ❌:** Caller number stored correctly?  
**Check ✅ / ❌:** Correct location_id on call record?

---

#### Step 6 — Verify call appears in dashboard

1. Log in to the dashboard as a staff user for **Location A**
2. Navigate to the calls/phone section
3. Find the inbound call from Step 3

**Expected:** The call is visible, shows the caller number, duration, and is linked to Location A only (not visible in Location B's dashboard).

**Check ✅ / ❌:** Call visible in correct location dashboard?  
**Check ✅ / ❌:** Call NOT visible in other location's dashboard?

---

#### Step 7 — Initiate an outbound call from dashboard

1. From the dashboard (logged in as Location A staff), initiate an outbound call to a real phone number
2. The call should ring on the real phone
3. Check the caller ID shown on the receiving phone — it must be Location A's Telnyx number

```bash
php artisan tinker
```

```php
$call = \App\Models\CallSession::where('direction', 'outbound')->latest()->first();
dump($call?->caller_number, $call?->called_number, $call?->location_id);
```

**Check ✅ / ❌:** Outbound call initiated successfully?  
**Check ✅ / ❌:** Correct location Telnyx number shown as caller ID?  
**Check ✅ / ❌:** Call log saved with correct location_id?

---

#### Step 8 — Multi-location separation test

1. Call **Location B's** Telnyx number from a real phone
2. Verify the call log is linked to Location B, not Location A

```bash
php artisan tinker
```

```php
// Should show Location B's ID
$call = \App\Models\CallSession::latest()->first();
dump($call?->location_id);

// Confirm Location A staff cannot see Location B's calls
$locationAId = 1; // replace with real ID
$locationBId = 2; // replace with real ID
dump(\App\Models\CallSession::where('location_id', $locationAId)->count()); // should NOT include Location B calls
```

**Check ✅ / ❌:** No mix between locations?

---

### Telnyx result checklist

```
[ ] inbound call         — calling the number triggers backend webhook
[ ] correct location     — called number → correct location_id
[ ] caller stored        — caller phone number saved in call_sessions
[ ] dashboard log        — call visible in correct location dashboard
[ ] outbound call        — call initiated from dashboard reaches real phone
[ ] correct caller ID    — location's Telnyx number shown as caller ID
[ ] multi-location       — no mix between locations
```

---

## 3. Common Failure Points to Check

### WhatsApp

| Symptom | Likely cause | Where to look |
|---------|-------------|---------------|
| Webhook not received | Wrong webhook URL in 360dialog dashboard | 360dialog portal → webhook settings |
| User not found | Phone stored in wrong format | `users.phone` column — check E.164 format |
| Location not found | User has no `client_location_id` or no location pivot | `user_locations` pivot table |
| AI reply not sent | `ProcessWhatsAppWebhook` job failed | `failed_jobs` table, Laravel logs |
| Message not in DB | Job dispatched but not processed | Check queue worker is running |
| Dashboard empty | Conversation linked to wrong location | `conversations.location_id` column |

### Telnyx

| Symptom | Likely cause | Where to look |
|---------|-------------|---------------|
| Webhook not received | Wrong webhook URL in Telnyx portal | Telnyx portal → phone numbers → webhooks |
| Location not found | `telnyx_number` not set on location | `locations.telnyx_number` column |
| Wrong location on call | Number shared between locations | Each location must have a unique number |
| Outbound uses wrong number | `location_id` not passed to `InitiateOutboundCall` job | `app/Jobs/InitiateOutboundCall.php` |
| Call not in dashboard | `call_sessions.location_id` is null | Check `ProcessTelnyxWebhook` job logic |

---

## 4. Quick DB Checks

```bash
# Check recent webhook events
php artisan tinker --execute="
\App\Models\WebhookEvent::latest()->take(10)->get(['id','source','created_at','processed_at'])->each(fn(\$w) => dump(\$w->toArray()));
"

# Check recent messages
php artisan tinker --execute="
\App\Models\Message::latest()->take(10)->get(['id','direction','channel','body','created_at'])->each(fn(\$m) => dump(\$m->toArray()));
"

# Check recent call sessions
php artisan tinker --execute="
\App\Models\CallSession::latest()->take(10)->get(['id','direction','caller_number','called_number','location_id','created_at'])->each(fn(\$c) => dump(\$c->toArray()));
"

# Check failed jobs
php artisan tinker --execute="
\Illuminate\Support\Facades\DB::table('failed_jobs')->latest()->take(5)->get()->each(fn(\$j) => dump(\$j));
"
```

---

## 5. Developer Feedback Template

After completing all tests, report results using this format:

### WhatsApp / 360dialog

```
sent                ✅ / ❌  — notes:
inbound webhook     ✅ / ❌  — notes:
user match          ✅ / ❌  — notes:
location match      ✅ / ❌  — notes:
AI reply            ✅ / ❌  — notes:
dashboard visibility ✅ / ❌ — notes:
```

### Telnyx

```
inbound call        ✅ / ❌  — notes:
correct location    ✅ / ❌  — notes:
caller stored       ✅ / ❌  — notes:
outbound call       ✅ / ❌  — notes:
correct caller ID   ✅ / ❌  — notes:
dashboard log       ✅ / ❌  — notes:
multi-location sep. ✅ / ❌  — notes:
```

---

## 6. Important Notes

- **Do not only test API response "200 OK"** — test the full chain on real devices
- **Use a real phone** for both WhatsApp and Telnyx tests
- **Log in to the dashboard** and verify visibility after each test
- **Check the `failed_jobs` table** after every test — silent failures hide here
- **Run the queue worker** during testing: `php artisan queue:work --tries=3`
- **Check Laravel logs** in real time: `tail -f storage/logs/laravel.log`
