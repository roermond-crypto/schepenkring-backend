# Smart Widget + Booking System — Technical Plan

> **Goal:** One smart widget on schepenkring.nl that automatically knows the correct location, lets users chat, book, and complete tasks — without ever asking a client to choose a location manually.

---

## Table of Contents

1. [Current State Analysis](#1-current-state-analysis)
2. [Client Flow — Remove Location Selection](#2-client-flow--remove-location-selection)
3. [Widget Architecture — Tabs + Dynamic Context](#3-widget-architecture--tabs--dynamic-context)
4. [Booking / Calendar System](#4-booking--calendar-system)
5. [Chat-Based Booking (AI Intent Detection)](#5-chat-based-booking-ai-intent-detection)
6. [One Booking Backend — Two Frontends](#6-one-booking-backend--two-frontends)
7. [Tasks Inside Widget](#7-tasks-inside-widget)
8. [Dashboard Visibility](#8-dashboard-visibility)
9. [Database Changes](#9-database-changes)
10. [Backend Implementation Plan](#10-backend-implementation-plan)
11. [API Endpoints](#11-api-endpoints)
12. [Implementation Order](#12-implementation-order)

---

## 1. Current State Analysis

### What already exists

| Component | Status | Notes |
|---|---|---|
| `ChatWidgetController::init()` | ✅ Exists | Accepts `harbor_id` + `visitor_id`, issues session JWT |
| `ChatConversationService` | ✅ Exists | Creates/reuses conversations with `location_id`, `boat_id` |
| `Conversation` model | ✅ Exists | Has `location_id`, `boat_id`, `user_id`, `contact_id` |
| `User::resolvedLocationId()` | ✅ Exists | Clients → `client_location_id`, Employees → first location |
| `User::isClient()` / `isAdmin()` | ✅ Exists | Role detection ready |
| `Task` model | ✅ Exists | Has `location_id`, `yacht_id`, `client_visible` flag |
| `Location` model | ✅ Exists | Has `chat_widget_enabled`, `chat_widget_theme`, `chat_widget_welcome_text` |
| `HarborChannel` model | ✅ Exists | Per-location WhatsApp + Telnyx config |
| `Yacht` model | ✅ Exists | Has `location_id`, `booking_duration_minutes` |
| `YachtAvailabilityRule` | ✅ Exists | Availability rules per yacht |
| Widget settings migration | ✅ Exists | `chat_widget_enabled`, `chat_widget_theme`, `chat_widget_welcome_text` on locations |

### What is missing

| Component | Status | Notes |
|---|---|---|
| Booking/appointment model | ❌ Missing | No `bookings` / `appointments` table yet |
| Location booking settings | ❌ Missing | No `booking_settings` on locations |
| Widget tab system | ❌ Missing | Widget only has chat, no tabs |
| AI booking intent detection | ❌ Missing | No booking intent handler in chat |
| Widget context API | ❌ Missing | No single endpoint to load all widget context by `boat_id` |
| Client auto-location API | ❌ Missing | No endpoint that returns location from boat/booking context |
| Task widget endpoint | ❌ Missing | No public endpoint for client-visible tasks |

---

## 2. Client Flow — Remove Location Selection

### The rule

```
UserType::CLIENT  →  location_id is ALWAYS auto-resolved
UserType::EMPLOYEE / ADMIN  →  location selector shown
```

### How location is resolved for clients (priority order)

```
1. boat_id in URL/context       → yacht.location_id
2. booking_id in URL/context    → booking.location_id
3. invite/signup context        → user.client_location_id
4. authenticated user           → user.client_location_id  (User::resolvedLocationId())
5. fallback                     → first active location (existing behavior)
```

### Backend change: `ChatWidgetController::init()`

**Current behavior:** Accepts `harbor_id` from the request, falls back to first location.

**New behavior:** Accept `boat_id` as an additional context signal. Resolve `location_id` server-side. Return the resolved `location_id` + full location settings to the frontend so it never needs to ask.

```php
// New init() logic (pseudocode)
public function init(Request $request): JsonResponse
{
    $boatId    = $request->input('boat_id');
    $harborId  = $request->input('harbor_id');
    $visitorId = $request->input('visitor_id') ?: Str::uuid();

    // Resolve location from context
    $locationId = $this->resolveLocationId($boatId, $harborId, $request->user());

    // Load full location settings for the widget
    $location = Location::with(['harborChannels'])->find($locationId);
    $settings = $this->buildWidgetSettings($location, $boatId);

    return response()->json([
        'visitor_id'   => $visitorId,
        'session_id'   => Str::uuid(),
        'session_jwt'  => $this->issueJwt($visitorId, $locationId, $boatId),
        'location_id'  => $locationId,
        'boat_id'      => $boatId,
        'settings'     => $settings,   // branding, tabs, booking config, etc.
    ]);
}

private function resolveLocationId(?int $boatId, ?int $harborId, ?User $user): int
{
    // 1. From boat
    if ($boatId) {
        $yacht = Yacht::find($boatId);
        if ($yacht?->location_id) return $yacht->location_id;
    }

    // 2. From explicit harbor_id (staff/admin use)
    if ($harborId) return $harborId;

    // 3. From authenticated client
    if ($user?->isClient() && $user->client_location_id) {
        return $user->client_location_id;
    }

    // 4. Fallback
    return Location::query()->value('id') ?? 1;
}
```

### Frontend rule (for the frontend team)

```
IF user.type === 'client':
    NEVER show location selector
    ALWAYS use location_id from widget init response

IF user.type === 'employee' OR 'admin':
    Show location selector (existing behavior)
```

---

## 3. Widget Architecture — Tabs + Dynamic Context

### Widget tabs

The widget on schepenkring.nl must have three tabs. Each tab loads its data from the same `location_id` + `boat_id` context resolved at init time.

```
┌─────────────────────────────────────────┐
│  [💬 Chat]  [✅ Tasks]  [📅 Booking]    │
├─────────────────────────────────────────┤
│                                         │
│  Tab content loads from:                │
│  - location_id (resolved at init)       │
│  - boat_id (from URL/context)           │
│                                         │
└─────────────────────────────────────────┘
```

### Widget settings response (from `init()`)

The `settings` object returned by `init()` drives everything the frontend needs:

```json
{
  "visitor_id": "uuid",
  "session_id": "uuid",
  "session_jwt": "...",
  "location_id": 1,
  "boat_id": 42,
  "settings": {
    "location": {
      "id": 1,
      "name": "Schepenkring Rotterdam",
      "theme": "ocean",
      "welcome_text": "Welkom! Hoe kunnen we u helpen?",
      "language": "nl",
      "logo_url": null
    },
    "tabs": {
      "chat": {
        "enabled": true,
        "welcome_text": "Stel uw vraag..."
      },
      "tasks": {
        "enabled": true,
        "label": "Mijn taken"
      },
      "booking": {
        "enabled": true,
        "label": "Afspraak maken",
        "appointment_types": [...],
        "opening_hours": {...},
        "booking_advance_days": 30,
        "min_notice_hours": 2
      }
    },
    "whatsapp_enabled": true,
    "phone_enabled": true
  }
}
```

### Location model additions needed

The `locations` table needs booking-specific settings. See [§9 Database Changes](#9-database-changes).

---

## 4. Booking / Calendar System

### Core concept

Bookings are **location-driven**. Each location defines:
- Available appointment types (viewing, proefvaart, inspection, etc.)
- Duration per type
- Opening hours (per day of week)
- Blocked times / holidays
- Advance booking window (e.g. max 30 days ahead)
- Minimum notice (e.g. at least 2 hours ahead)
- Confirmation text (per language)
- Auto-confirm vs. manual approval

### New models needed

#### `Booking` (appointments table)

```php
// bookings table
Schema::create('bookings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('location_id')->constrained('locations');
    $table->foreignId('yacht_id')->nullable()->constrained('yachts');
    $table->foreignId('user_id')->nullable()->constrained('users');       // client
    $table->foreignId('contact_id')->nullable()->constrained('contacts');
    $table->foreignId('conversation_id')->nullable();                     // linked chat
    $table->foreignId('created_by_user_id')->nullable();                  // staff who created it
    $table->string('appointment_type');                                   // viewing, proefvaart, inspection
    $table->string('status')->default('pending');                         // pending, confirmed, cancelled, completed
    $table->string('source')->default('widget');                          // widget, chat, dashboard, api
    $table->dateTime('starts_at');
    $table->dateTime('ends_at');
    $table->integer('duration_minutes');
    $table->string('name')->nullable();
    $table->string('email')->nullable();
    $table->string('phone')->nullable();
    $table->text('notes')->nullable();
    $table->string('cancellation_reason')->nullable();
    $table->dateTime('confirmed_at')->nullable();
    $table->dateTime('cancelled_at')->nullable();
    $table->json('metadata')->nullable();                                 // intent, channel, boat_id, etc.
    $table->timestamps();
});
```

#### `LocationBookingSetting` (per-location booking config)

```php
// location_booking_settings table
Schema::create('location_booking_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('location_id')->unique()->constrained('locations');
    $table->boolean('booking_enabled')->default(true);
    $table->boolean('auto_confirm')->default(false);
    $table->integer('advance_booking_days')->default(30);
    $table->integer('min_notice_hours')->default(2);
    $table->json('appointment_types')->nullable();   // [{type, label_nl, label_en, duration_minutes}]
    $table->json('opening_hours')->nullable();       // {mon: {open: "09:00", close: "17:00"}, ...}
    $table->json('blocked_dates')->nullable();       // ["2026-12-25", "2026-01-01"]
    $table->json('confirmation_texts')->nullable();  // {nl: "...", en: "...", de: "..."}
    $table->timestamps();
});
```

### Availability logic

```
GET /api/public/widget/availability?location_id=1&boat_id=42&type=viewing&date=2026-03-25

Response:
{
  "date": "2026-03-25",
  "slots": [
    {"time": "09:00", "available": true},
    {"time": "10:00", "available": true},
    {"time": "11:00", "available": false},  // already booked
    {"time": "14:00", "available": true}
  ]
}
```

**Slot generation logic:**
1. Load `LocationBookingSetting` for the location
2. Check `opening_hours` for the requested day
3. Generate slots based on `duration_minutes` for the appointment type
4. Subtract existing `bookings` that overlap (`status != 'cancelled'`)
5. Apply `min_notice_hours` (remove slots too close to now)
6. Apply `blocked_dates`

### Booking flow (Calendar tab)

```
User opens Booking tab
        ↓
Load appointment types from location settings
        ↓
User selects type + date
        ↓
GET /api/public/widget/availability → show available slots
        ↓
User selects slot
        ↓
Show confirmation summary:
  "Bezichtiging op vrijdag 27 maart om 10:00
   Boot: Beneteau Oceanis 40 (SK-2026-ABC123)
   Locatie: Schepenkring Rotterdam"
        ↓
User confirms
        ↓
POST /api/public/widget/bookings → create booking
        ↓
Store in bookings table (source='widget')
Link to conversation_id if chat is open
        ↓
Return booking confirmation + booking_id
        ↓
Show in dashboard (admin sees it immediately)
```

---

## 5. Chat-Based Booking (AI Intent Detection)

### How it works

When a user sends a chat message, the AI pipeline checks for booking intent **before** generating a generic reply. If booking intent is detected, a structured booking flow is triggered instead.

### Intent types to detect

| User message | Intent | Action |
|---|---|---|
| "Ik wil de boot vrijdag bekijken" | `booking.create` | Check availability, suggest slots |
| "Kan ik morgen langskomen?" | `booking.create` | Check availability for tomorrow |
| "Verzet mijn afspraak naar maandag" | `booking.reschedule` | Load existing booking, suggest new slots |
| "Annuleer mijn afspraak" | `booking.cancel` | Confirm + cancel booking |
| "Wanneer kan ik komen?" | `booking.availability` | Show available slots |
| "Kan ik een proefvaart doen?" | `booking.create` | Type=proefvaart, check availability |

### AI booking intent handler (new service)

```php
// App\Services\BookingIntentService

class BookingIntentService
{
    public function detectIntent(string $text, Conversation $conversation): ?BookingIntent
    {
        // Call OpenAI with a structured prompt:
        // "Does this message contain a booking intent?
        //  If yes, return: {intent_type, preferred_date, preferred_time, appointment_type}"
        // Return null if no booking intent detected
    }

    public function handleIntent(BookingIntent $intent, Conversation $conversation, Request $request): array
    {
        return match ($intent->type) {
            'booking.create'       => $this->handleCreate($intent, $conversation),
            'booking.reschedule'   => $this->handleReschedule($intent, $conversation),
            'booking.cancel'       => $this->handleCancel($intent, $conversation),
            'booking.availability' => $this->handleAvailability($intent, $conversation),
            default                => ['handled' => false],
        };
    }
}
```

### Chat booking flow

```
User: "Ik wil de boot vrijdag bekijken"
        ↓
ProcessWhatsAppWebhook / ChatMessageController
        ↓
BookingIntentService::detectIntent()
  → intent_type: booking.create
  → preferred_date: "2026-03-27" (next Friday)
  → appointment_type: "viewing"
        ↓
Load location booking settings (from conversation.location_id)
Check availability for Friday
        ↓
AI generates response with available slots:
  "Vrijdag 27 maart zijn de volgende tijden beschikbaar:
   • 09:00 ✓
   • 10:00 ✓
   • 14:00 ✓
   Welk tijdstip past u het beste?"
        ↓
Store message with metadata:
  {
    "intent_type": "booking.create",
    "booking_context": {
      "date": "2026-03-27",
      "type": "viewing",
      "boat_id": 42,
      "location_id": 1,
      "available_slots": ["09:00", "10:00", "14:00"]
    }
  }
        ↓
User: "10 uur graag"
        ↓
AI detects slot selection (context from previous message)
Shows confirmation summary:
  "Ik maak een afspraak voor u:
   📅 Vrijdag 27 maart om 10:00
   🚢 Beneteau Oceanis 40
   📍 Schepenkring Rotterdam
   
   Bevestigen? (ja / nee)"
        ↓
User: "ja"
        ↓
POST /api/bookings (internal)
  source: 'chat'
  conversation_id: linked
  booking stored in DB
        ↓
AI confirms:
  "✅ Afspraak bevestigd! U ontvangt een bevestiging per e-mail."
```

### Integration point in `ProcessWhatsAppWebhook` and `ChatMessageController`

The booking intent check is inserted **before** the generic AI reply:

```php
// In ProcessWhatsAppWebhook::handleInboundMessage() — after storing inbound message:

if ($text) {
    // 1. Check for booking intent FIRST
    $bookingIntent = $bookingIntentService->detectIntent($text, $freshConversation);
    if ($bookingIntent) {
        $bookingIntentService->handleIntent($bookingIntent, $freshConversation, $request);
        return; // Booking handler generates the reply — skip generic AI
    }

    // 2. Fall through to generic AI reply
    if ($ai->shouldAutoReply($freshConversation)) {
        $ai->generateForVisitorMessage($freshConversation, $saved, $request, [...]);
    }
}
```

### Confirmation before action

**Rule:** AI must NEVER create, reschedule, or cancel a booking without showing a confirmation summary first and receiving explicit user confirmation (`ja` / `yes` / `bevestig`).

```php
// BookingIntentService tracks pending confirmations in conversation metadata:
$conversation->metadata = array_merge($conversation->metadata ?? [], [
    'pending_booking_confirmation' => [
        'intent_type'  => 'booking.create',
        'date'         => '2026-03-27',
        'time'         => '10:00',
        'type'         => 'viewing',
        'boat_id'      => 42,
        'location_id'  => 1,
        'expires_at'   => now()->addMinutes(10)->toIso8601String(),
    ],
]);
```

---

## 6. One Booking Backend — Two Frontends

### Architecture

```
                    ┌─────────────────────────────┐
                    │     BookingService           │
                    │  (single source of truth)    │
                    │                              │
                    │  - checkAvailability()       │
                    │  - createBooking()           │
                    │  - rescheduleBooking()       │
                    │  - cancelBooking()           │
                    │  - getBookingsForLocation()  │
                    └──────────┬──────────────────┘
                               │
              ┌────────────────┴────────────────┐
              │                                 │
    ┌─────────▼──────────┐           ┌──────────▼──────────┐
    │  Booking/Calendar  │           │    Chat Tab          │
    │  Tab (widget)      │           │  (AI intent handler) │
    │                    │           │                      │
    │  User picks date   │           │  User types message  │
    │  + slot visually   │           │  AI detects intent   │
    └────────────────────┘           └─────────────────────┘
              │                                 │
              └────────────────┬────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │   bookings table    │
                    │  (single DB table)  │
                    └─────────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │   Dashboard         │
                    │  (admin/staff view) │
                    └─────────────────────┘
```

### Key rule

Both the Calendar tab and the Chat tab call the **same** `BookingService` methods. There is no separate "chat booking" logic and "calendar booking" logic. The only difference is the `source` field on the booking record (`widget` vs `chat`).

```php
// Both frontends call the same service:
$booking = $bookingService->createBooking([
    'location_id'      => 1,
    'yacht_id'         => 42,
    'user_id'          => $user->id,
    'contact_id'       => $contact->id,
    'conversation_id'  => $conversation->id,  // null if from calendar tab without active chat
    'appointment_type' => 'viewing',
    'starts_at'        => '2026-03-27 10:00:00',
    'duration_minutes' => 60,
    'source'           => 'chat',  // or 'widget'
    'name'             => 'Jan de Vries',
    'email'            => 'jan@example.com',
    'phone'            => '+31612345678',
    'metadata'         => [
        'intent_type' => 'booking.create',
        'channel'     => 'whatsapp',
        'boat_id'     => 42,
    ],
]);
```

---

## 7. Tasks Inside Widget

### Current state

`Task` model already has:
- `client_visible` (boolean) — controls whether a task is shown to the client
- `yacht_id` — links task to a specific boat
- `location_id` — scopes task to a location
- `status`, `due_date`, `title`, `description`

### New public endpoint needed

```
GET /api/public/widget/tasks?boat_id=42&visitor_id=xxx
Authorization: session_jwt (from widget init)

Response:
{
  "tasks": [
    {
      "id": 15,
      "title": "Upload eigendomsbewijs",
      "description": "Voeg het eigendomsbewijs van uw boot toe.",
      "status": "open",
      "due_date": "2026-03-30",
      "type": "document_upload",
      "action_url": "/dashboard/boats/42/documents"
    },
    {
      "id": 16,
      "title": "Controleer bootgegevens",
      "description": "Controleer of alle gegevens correct zijn.",
      "status": "completed",
      "due_date": null,
      "type": "review"
    }
  ],
  "summary": {
    "total": 5,
    "completed": 2,
    "open": 3
  }
}
```

### Task visibility rules

```php
// Only return tasks where:
Task::where('yacht_id', $boatId)
    ->where('client_visible', true)
    ->whereIn('status', ['open', 'in_progress', 'completed'])
    ->orderByRaw("FIELD(status, 'open', 'in_progress', 'completed')")
    ->orderBy('due_date')
    ->get();
```

### Task completion from widget

Clients should be able to mark simple tasks as done from the widget:

```
PATCH /api/public/widget/tasks/{id}/complete
Authorization: session_jwt

Body: { "boat_id": 42 }
```

---

## 8. Dashboard Visibility

### Everything must appear in the dashboard after any widget interaction

| Action | Dashboard shows |
|---|---|
| Chat message sent | Conversation with full message thread, linked to location + boat |
| Booking created via calendar tab | Booking record, linked to conversation if open |
| Booking created via chat | Booking record + chat message with booking summary |
| Booking rescheduled | Updated booking + system message in conversation |
| Booking cancelled | Cancelled booking + system message in conversation |
| Task completed | Task status updated, activity log entry |

### Booking in conversation thread

When a booking is created via chat, a system message is added to the conversation:

```php
// System message stored in messages table:
Message::create([
    'conversation_id' => $conversation->id,
    'sender_type'     => 'system',
    'message_type'    => 'booking_created',
    'text'            => 'Afspraak aangemaakt: Bezichtiging op vrijdag 27 maart om 10:00',
    'channel'         => $conversation->channel_origin,
    'metadata'        => [
        'booking_id'       => $booking->id,
        'appointment_type' => 'viewing',
        'starts_at'        => '2026-03-27 10:00:00',
        'boat_id'          => 42,
        'location_id'      => 1,
        'source'           => 'chat',
        'intent_type'      => 'booking.create',
    ],
]);
```

### Dashboard booking view

Admin/staff can see bookings in two places:
1. **Conversation thread** — system messages show booking actions inline
2. **Bookings list** — dedicated view at `/admin/bookings?location_id=1`

---

## 9. Database Changes

### Migration 1: `location_booking_settings` table (new)

```php
Schema::create('location_booking_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('location_id')->unique()->constrained('locations')->cascadeOnDelete();
    $table->boolean('booking_enabled')->default(true);
    $table->boolean('auto_confirm')->default(false);
    $table->integer('advance_booking_days')->default(30);
    $table->integer('min_notice_hours')->default(2);
    $table->integer('slot_interval_minutes')->default(60);
    $table->json('appointment_types')->nullable();
    // Format: [{"type":"viewing","label_nl":"Bezichtiging","label_en":"Viewing","duration_minutes":60}]
    $table->json('opening_hours')->nullable();
    // Format: {"mon":{"open":"09:00","close":"17:00"},"tue":{...},...,"sun":null}
    $table->json('blocked_dates')->nullable();
    // Format: ["2026-12-25","2026-01-01"]
    $table->json('confirmation_texts')->nullable();
    // Format: {"nl":"Uw afspraak is bevestigd...","en":"Your appointment..."}
    $table->timestamps();
});
```

### Migration 2: `bookings` table (new)

```php
Schema::create('bookings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('location_id')->constrained('locations');
    $table->foreignId('yacht_id')->nullable()->constrained('yachts')->nullOnDelete();
    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
    $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
    $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('appointment_type');
    $table->string('status')->default('pending');
    // pending | confirmed | cancelled | completed | no_show
    $table->string('source')->default('widget');
    // widget | chat | dashboard | api | whatsapp
    $table->dateTime('starts_at');
    $table->dateTime('ends_at');
    $table->integer('duration_minutes');
    $table->string('name')->nullable();
    $table->string('email')->nullable();
    $table->string('phone')->nullable();
    $table->text('notes')->nullable();
    $table->string('cancellation_reason')->nullable();
    $table->dateTime('confirmed_at')->nullable();
    $table->dateTime('cancelled_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['location_id', 'starts_at']);
    $table->index(['yacht_id', 'starts_at']);
    $table->index(['user_id', 'starts_at']);
    $table->index('status');
});
```

### Migration 3: Add booking + widget tab settings to `locations` table

```php
Schema::table('locations', function (Blueprint $table) {
    // Widget tab visibility
    $table->boolean('widget_tasks_tab_enabled')->default(true);
    $table->boolean('widget_booking_tab_enabled')->default(true);
    $table->string('widget_language')->default('nl');
    $table->string('widget_logo_url')->nullable();

    // Contact info shown in widget
    $table->string('contact_phone')->nullable();
    $table->string('contact_email')->nullable();
    $table->string('contact_whatsapp')->nullable();
    $table->string('address')->nullable();
    $table->string('city')->nullable();
});
```

### Migration 4: Add `metadata` to `conversations` table

```php
Schema::table('conversations', function (Blueprint $table) {
    $table->json('metadata')->nullable()->after('lead_id');
    // Used for: pending_booking_confirmation, widget_context, etc.
});
```

### Migration 5: Add `booking_id` to `messages` table

```php
Schema::table('messages', function (Blueprint $table) {
    $table->foreignId('booking_id')->nullable()->after('conversation_id')
        ->constrained('bookings')->nullOnDelete();
});
```

---

## 10. Backend Implementation Plan

### Phase 1 — Foundation (do first)

#### 1.1 Migrations
Run all 5 migrations from §9.

#### 1.2 New Models

**`Booking` model:**
```php
class Booking extends Model
{
    protected $fillable = [
        'location_id', 'yacht_id', 'user_id', 'contact_id',
        'conversation_id', 'created_by_user_id',
        'appointment_type', 'status', 'source',
        'starts_at', 'ends_at', 'duration_minutes',
        'name', 'email', 'phone', 'notes',
        'cancellation_reason', 'confirmed_at', 'cancelled_at', 'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function location(): BelongsTo { ... }
    public function yacht(): BelongsTo { ... }
    public function user(): BelongsTo { ... }
    public function contact(): BelongsTo { ... }
    public function conversation(): BelongsTo { ... }
    public function createdBy(): BelongsTo { ... }
}
```

**`LocationBookingSetting` model:**
```php
class LocationBookingSetting extends Model
{
    protected $fillable = [
        'location_id', 'booking_enabled', 'auto_confirm',
        'advance_booking_days', 'min_notice_hours', 'slot_interval_minutes',
        'appointment_types', 'opening_hours', 'blocked_dates', 'confirmation_texts',
    ];

    protected $casts = [
        'booking_enabled' => 'boolean',
        'auto_confirm' => 'boolean',
        'appointment_types' => 'array',
        'opening_hours' => 'array',
        'blocked_dates' => 'array',
        'confirmation_texts' => 'array',
    ];

    public function location(): BelongsTo { ... }
}
```

#### 1.3 `BookingService` (core service)

```php
// App\Services\BookingService

class BookingService
{
    public function getAvailableSlots(
        int $locationId,
        string $date,
        string $appointmentType,
        ?int $yachtId = null
    ): array;

    public function createBooking(array $data): Booking;

    public function confirmBooking(Booking $booking, ?User $confirmedBy = null): Booking;

    public function rescheduleBooking(Booking $booking, string $newStartsAt): Booking;

    public function cancelBooking(Booking $booking, string $reason, ?User $cancelledBy = null): Booking;

    public function getBookingsForLocation(int $locationId, array $filters = []): Collection;

    private function generateSlots(LocationBookingSetting $settings, string $date, int $durationMinutes): array;

    private function filterBookedSlots(array $slots, int $locationId, string $date, int $durationMinutes): array;
}
```

#### 1.4 Update `ChatWidgetController::init()`

Add `boat_id` resolution and return full `settings` object (see §2).

#### 1.5 Update `Location` model

Add relationships and new fillable fields:

```php
public function bookingSetting(): HasOne
{
    return $this->hasOne(LocationBookingSetting::class);
}

public function bookings(): HasMany
{
    return $this->hasMany(Booking::class);
}

public function harborChannels(): HasMany
{
    return $this->hasMany(HarborChannel::class, 'harbor_id');
}
```

---

### Phase 2 — Public Widget API

#### 2.1 `WidgetContextController` (new)

```php
// GET /api/public/widget/context?boat_id=42&location_id=1
// Returns: location settings, booking config, tab visibility
```

#### 2.2 `WidgetAvailabilityController` (new)

```php
// GET /api/public/widget/availability
// Params: location_id, boat_id (optional), type, date
// Returns: available time slots
```

#### 2.3 `WidgetBookingController` (new)

```php
// POST /api/public/widget/bookings       — create booking
// GET  /api/public/widget/bookings/{id}  — get booking details
// PATCH /api/public/widget/bookings/{id}/cancel — cancel booking
```

#### 2.4 `WidgetTaskController` (new)

```php
// GET  /api/public/widget/tasks?boat_id=42
// PATCH /api/public/widget/tasks/{id}/complete
```

---

### Phase 3 — Chat Booking Integration

#### 3.1 `BookingIntentService` (new)

Detects booking intent from chat messages using OpenAI structured output.

```php
class BookingIntentService
{
    public function detectIntent(string $text, Conversation $conversation): ?BookingIntent;
    public function handleIntent(BookingIntent $intent, Conversation $conversation, Request $request): array;
    public function hasPendingConfirmation(Conversation $conversation): bool;
    public function processPendingConfirmation(string $text, Conversation $conversation, Request $request): bool;
}
```

#### 3.2 Update `ProcessWhatsAppWebhook`

Insert booking intent check before generic AI reply (see §5).

#### 3.3 Update `ChatMessageController` (web widget chat)

Same booking intent check for web widget messages.

---

### Phase 4 — Dashboard Booking Views

#### 4.1 `BookingController` (authenticated, admin/staff)

```php
// GET    /api/bookings                  — list bookings for location
// GET    /api/bookings/{id}             — show booking
// POST   /api/bookings                  — create booking (from dashboard)
// PATCH  /api/bookings/{id}             — update booking
// POST   /api/bookings/{id}/confirm     — confirm booking
// POST   /api/bookings/{id}/cancel      — cancel booking
// POST   /api/bookings/{id}/reschedule  — reschedule booking
```

#### 4.2 `LocationBookingSettingController` (admin)

```php
// GET /api/admin/locations/{id}/booking-settings
// PUT /api/admin/locations/{id}/booking-settings
```

---

## 11. API Endpoints

### New public endpoints (no auth required)

```
POST   /api/public/widget/init                    — init widget (updated, returns settings)
GET    /api/public/widget/context                 — load widget context by boat_id/location_id
GET    /api/public/widget/availability            — get available booking slots
POST   /api/public/widget/bookings                — create booking
GET    /api/public/widget/bookings/{id}           — get booking (by token or session_jwt)
PATCH  /api/public/widget/bookings/{id}/cancel    — cancel booking
GET    /api/public/widget/tasks                   — get client-visible tasks for boat
PATCH  /api/public/widget/tasks/{id}/complete     — mark task complete
```

### New authenticated endpoints (staff/admin)

```
GET    /api/bookings                              — list bookings (location-scoped)
GET    /api/bookings/{id}                         — show booking
POST   /api/bookings                              — create booking from dashboard
PATCH  /api/bookings/{id}                         — update booking
POST   /api/bookings/{id}/confirm                 — confirm booking
POST   /api/bookings/{id}/cancel                  — cancel booking
POST   /api/bookings/{id}/reschedule              — reschedule booking
GET    /api/admin/locations/{id}/booking-settings — get booking settings
PUT    /api/admin/locations/{id}/booking-settings — update booking settings
```

### Updated existing endpoints

```
POST   /api/public/widget/init                    — now accepts boat_id, returns settings object
GET    /api/admin/locations/{id}/widget-settings  — extend to include booking + tab settings
PUT    /api/admin/locations/{id}/widget-settings  — extend to include booking + tab settings
```

---

## 12. Implementation Order

Work through these in order. Each phase is independently deployable.

### Phase 1 — Foundation (Week 1)
- [ ] Run migrations (§9): `location_booking_settings`, `bookings`, locations columns, conversations.metadata, messages.booking_id
- [ ] Create `Booking` model + relationships
- [ ] Create `LocationBookingSetting` model + relationships
- [ ] Update `Location` model (add `bookingSetting()`, `bookings()`, `harborChannels()` relations)
- [ ] Create `BookingService` with `createBooking()`, `getAvailableSlots()`, `cancelBooking()`, `rescheduleBooking()`
- [ ] Update `ChatWidgetController::init()` — add `boat_id` resolution, return `settings` object
- [ ] Seed default `LocationBookingSetting` for each existing location

### Phase 2 — Public Widget API (Week 1–2)
- [ ] `WidgetContextController` — `GET /api/public/widget/context`
- [ ] `WidgetAvailabilityController` — `GET /api/public/widget/availability`
- [ ] `WidgetBookingController` — create / get / cancel booking
- [ ] `WidgetTaskController` — list + complete client-visible tasks
- [ ] Add session_jwt validation middleware for widget endpoints

### Phase 3 — Chat Booking (Week 2)
- [ ] `BookingIntentService` — OpenAI intent detection with structured output
- [ ] `BookingIntent` DTO class
- [ ] Update `ProcessWhatsAppWebhook` — insert intent check before AI reply
- [ ] Update `ChatMessageController` — same intent check for web widget
- [ ] Add `pending_booking_confirmation` state to conversation metadata
- [ ] Add `booking_created` / `booking_cancelled` / `booking_rescheduled` system message types
- [ ] Test full chat booking flow end-to-end

### Phase 4 — Dashboard (Week 2–3)
- [ ] `BookingController` (authenticated) — full CRUD + confirm/cancel/reschedule
- [ ] `LocationBookingSettingController` (admin) — get/update booking settings
- [ ] Extend `GET /api/admin/locations/{id}/widget-settings` to include booking config
- [ ] Verify booking system messages appear in conversation thread in dashboard
- [ ] Verify bookings are visible per location in admin view

### Phase 5 — Frontend (parallel with Phase 2–4)
- [ ] Update widget init call to pass `boat_id` from URL/context
- [ ] Remove location selector for `UserType::CLIENT`
- [ ] Add tab bar to widget (Chat / Tasks / Booking)
- [ ] Build Booking tab UI (appointment type → date picker → slot picker → confirm)
- [ ] Build Tasks tab UI (list + complete action)
- [ ] Handle `booking_created` system messages in chat thread
- [ ] Multi-language support for booking texts (NL/EN/DE)

---

## Metadata to Store on Every Booking/Chat Action

Always store the following in `metadata` fields for debugging and analytics:

```json
{
  "channel": "whatsapp | web_widget | dashboard | api",
  "intent_type": "booking.create | booking.reschedule | booking.cancel | booking.availability",
  "booking_id": 123,
  "boat_id": 42,
  "location_id": 1,
  "source": "chat | widget | dashboard",
  "language": "nl | en | de",
  "timestamps": {
    "intent_detected_at": "2026-03-27T09:00:00Z",
    "confirmation_sent_at": "2026-03-27T09:00:05Z",
    "confirmed_at": "2026-03-27T09:00:30Z"
  }
}
```

---

## Summary

| Requirement | Solution |
|---|---|
| Clients never choose location | `init()` resolves from `boat_id` → `yacht.location_id` → `user.client_location_id` |
| Widget loads per-location settings | `init()` returns full `settings` object including booking config |
| Widget tabs (Chat / Tasks / Booking) | New tab system driven by `settings.tabs` from init response |
| Booking calendar tab | `BookingService` + `LocationBookingSetting` + availability slots API |
| Chat-based booking | `BookingIntentService` intercepts messages before generic AI reply |
| One booking backend | Both Calendar tab and Chat tab call the same `BookingService` |
| Tasks in widget | `WidgetTaskController` returns `client_visible=true` tasks for `boat_id` |
| Dashboard visibility | System messages (`booking_created` etc.) + `BookingController` for staff |
| Confirmation before action | `pending_booking_confirmation` state in `conversation.metadata` |
| Multi-language | `confirmation_texts` JSON on `LocationBookingSetting`, `widget_language` on Location |
