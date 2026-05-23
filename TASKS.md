# Schepenkring Backend — Trello Task Tracker

| # | Task Title | Branch | PR Link | Status | Requires Frontend |
|---|------------|--------|---------|--------|-------------------|
| 279 | BIDS Page Visibility (location settings) | `feature/279-bids-page-visibility` | — | Pending | Yes |
| 270 | Fix "Report an Issue" Modal (async AI job) | `feature/270-report-issue-async-ai` | — | Pending | No |
| 268 | Seller Dashboard Aggregated API | `feature/268-seller-dashboard-api` | — | Pending | Yes |
| 265 | Onboarding Corrections (remove payment/contract/verification) | `feature/265-onboarding-corrections` | — | Pending | Yes |
| 266 | Bidding System (counter offer, chat, deal creation) | `feature/266-bidding-system` | — | Pending | Yes |
| 271 | Selectable AI Boat Matches | `feature/271-selectable-ai-boat-matches` | — | Pending | Yes |
| 267 | Client Onboarding & AI Draft Boat Flow | `feature/267-client-onboarding-ai-flow` | — | Pending | Yes |
| 269 | Dashboard Chat Upgrade (message types, Signhost, filters) | `feature/269-dashboard-chat-upgrade` | — | Pending | Yes |
| 272 | YachtShift Sync Layer (two-way, audit, conflict) | `feature/272-yachtshift-sync` | — | Pending | No |
| 273 | Fix AI Boat Library/Scraper (Pinecone re-index) | `feature/273-fix-ai-boat-scraper` | — | Pending | No |
| 274 | Full Seller Onboarding Test (Bayliner 2855) | `feature/274-seller-onboarding-e2e-test` | — | Pending | No |
| 275 | AI Helpdesk (Vonage + OpenAI Realtime) | `feature/275-ai-helpdesk` | — | Pending | Yes |
| 276 | Location-Based Bidding Modes | `feature/276-location-based-bidding` | — | Pending | Yes |
| 277 | Full Platform QA Checklist | `feature/277-platform-qa-checklist` | — | Pending | No |
| 281 | Client Contract Card Improvement | `feature/281-contract-card-improvement` | — | Pending | Yes |
| 148 | Co-pilot "Create Boat" Bug Fix | `fix/148-copilot-create-boat` | — | Pending | No |

---

## Task Details

### Card #279 — BIDS Page Visibility
**Branch:** `feature/279-bids-page-visibility`

**What was done:**
- Added `bids_page_enabled` flag to the location/harbour settings model and migration
- Exposed `GET /api/admin/locations/{id}/bids-settings` to read the flag
- Added `bids_page_enabled` to the `LocationWidgetSettingsController` so the public widget settings endpoint (`GET /api/public/locations/{id}/widget-settings`) returns it
- Frontend should hide the Bids nav item when the flag is `false`

**API Example:**
```http
GET /api/public/locations/1/widget-settings
Authorization: Bearer <token>

200 OK
{
  "bids_page_enabled": true,
  "direct_buyer_seller_chat_enabled": false,
  "seller_bid_notifications_enabled": true
}
```

---

### Card #270 — Fix "Report an Issue" Modal
**Branch:** `feature/270-report-issue-async-ai`

**What was done:**
- Created `Issue` model + migration (`issues` table)
- Created `IssueController` with `store` and `retryAi` actions
- Created `AnalyzeIssueWithAI` queued job that runs GPT analysis in the background
- `POST /api/issues` saves the issue synchronously and dispatches the job, returning `202 Accepted` immediately
- `POST /api/admin/issues/{id}/retry-ai` re-dispatches the job for admin retry

**API Example:**
```http
POST /api/issues
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "Boat listing not loading",
  "description": "When I click on my Bayliner 2855 listing, the page shows a blank screen.",
  "yacht_id": 42
}

202 Accepted
{
  "message": "Issue reported. AI analysis is running in the background.",
  "issue_id": 7
}
```

```http
POST /api/admin/issues/7/retry-ai
Authorization: Bearer <admin-token>

200 OK
{ "message": "AI analysis re-queued." }
```

---

### Card #268 — Seller Dashboard Aggregated API
**Branch:** `feature/268-seller-dashboard-api`

**What was done:**
- Created `SellerDashboardService` that aggregates: active listings count, total bids received, open conversations, pending tasks, recent bid activity, and revenue summary
- Created `SellerDashboardController` with a single `summary` action
- Added `GET /api/dashboard/seller/summary` route (auth + sanctum)
- Response is cached for 5 minutes per seller to avoid N+1 queries

**API Example:**
```http
GET /api/dashboard/seller/summary
Authorization: Bearer <token>

200 OK
{
  "listings": { "active": 3, "draft": 1, "sold": 12 },
  "bids": { "total_received": 47, "pending_review": 5, "accepted": 10 },
  "conversations": { "open": 8, "unread": 3 },
  "tasks": { "pending": 4, "overdue": 1 },
  "revenue": { "total_eur": 184500, "this_month_eur": 12000 },
  "recent_bids": [
    { "id": 99, "yacht_name": "Bayliner 2855", "amount": 24500, "bidder": "Jan de Vries", "created_at": "2026-05-23T10:00:00Z" }
  ]
}
```

---

### Card #265 — Onboarding Corrections
**Branch:** `feature/265-onboarding-corrections`

**What was done:**
- Removed payment session, Signhost contract, and iDIN verification steps from the seller onboarding flow — the CRUD engine (profile + KYC) is kept
- Fixed Google Maps Geocoding ZIP auto-fill: now sends `components=country:NL` to restrict results
- `seller-onboarding/status` no longer returns `payment_step`, `contract_step`, or `verification_step` keys
- Onboarding `submit` now goes straight from KYC → complete

**API Example:**
```http
GET /api/seller-onboarding/status
Authorization: Bearer <token>

200 OK
{
  "step": "kyc",
  "completed": false,
  "steps": {
    "profile": "complete",
    "kyc": "pending"
  }
}
```

---

### Card #266 — Bidding System
**Branch:** `feature/266-bidding-system`

**What was done:**
- Fixed `HandleBidEvents` listener to fire push notifications on new bids
- Added `POST /api/owner-bids/{id}/counter` endpoint for sellers to send counter offers
- Added automatic buyer↔seller chat thread creation when a bid is accepted
- Added deal creation (`Deal` model/migration) when seller accepts a bid
- Seller approval flow: `POST /api/owner-bids/{id}/accept` and `POST /api/owner-bids/{id}/reject`

**API Example:**
```http
POST /api/owner-bids/55/counter
Authorization: Bearer <seller-token>
Content-Type: application/json

{ "amount": 27000, "message": "We can meet at €27,000 including mooring." }

200 OK
{
  "id": 56,
  "parent_bid_id": 55,
  "type": "counter",
  "amount": 27000,
  "message": "We can meet at €27,000 including mooring.",
  "status": "pending"
}
```

```http
POST /api/owner-bids/55/accept
Authorization: Bearer <seller-token>

200 OK
{
  "deal_id": 12,
  "message": "Bid accepted. A deal has been created and buyer notified.",
  "chat_conversation_id": 88
}
```

---

### Card #271 — Selectable AI Boat Matches
**Branch:** `feature/271-selectable-ai-boat-matches`

**What was done:**
- Updated `YachtDraftController` to expose Pinecone match results as clickable cards
- Added `POST /api/admin/yachts/draft/{draftId}/select-reference-boat` to link a reference yacht to a draft
- Added `POST /api/admin/yachts/draft/{draftId}/ai-autofill` to auto-populate draft fields from a selected reference boat via GPT

**API Example:**
```http
POST /api/admin/yachts/draft/18/select-reference-boat
Authorization: Bearer <admin-token>
Content-Type: application/json

{ "reference_yacht_id": 304 }

200 OK
{ "message": "Reference boat linked.", "draft_id": 18, "reference_yacht_id": 304 }
```

```http
POST /api/admin/yachts/draft/18/ai-autofill
Authorization: Bearer <admin-token>

200 OK
{
  "filled_fields": ["length", "beam", "year", "engine_brand", "fuel_type"],
  "confidence": 0.91,
  "draft": { "id": 18, "length": 8.6, "beam": 2.9, "year": 1997, ... }
}
```

---

### Card #267 — Client Onboarding & AI Draft Boat Flow
**Branch:** `feature/267-client-onboarding-ai-flow`

**What was done:**
- Added frictionless registration endpoint `POST /api/onboarding/quick-register` (name + email only)
- Added `POST /api/onboarding/ai-draft` that accepts a free-text boat description and returns a pre-filled `YachtDraft`
- Added deeplink token system: `POST /api/onboarding/deeplink` generates a signed, time-limited URL (valid Mon–Sat 09:00–18:00 NL time)
- Thank-you page data endpoint: `GET /api/onboarding/thank-you`

**API Example:**
```http
POST /api/onboarding/ai-draft
Authorization: Bearer <token>
Content-Type: application/json

{
  "description": "I have a 1997 Bayliner 2855, white hull, twin Mercruiser engines, GPS, VHF, full canopy."
}

201 Created
{
  "draft_id": 29,
  "yacht": {
    "brand": "Bayliner",
    "model": "2855",
    "year": 1997,
    "hull_color": "white",
    "engines": [{ "brand": "Mercruiser", "count": 2 }],
    "equipment": ["GPS", "VHF", "Canopy"]
  },
  "confidence": 0.87
}
```

---

### Card #269 — Dashboard Chat Upgrade
**Branch:** `feature/269-dashboard-chat-upgrade`

**What was done:**
- Added `type` column to `chat_messages` (`chat | email | system | signhost | contract | boat | admin_note`)
- Created `SignhostMessageParser` to extract signing events from Signhost webhooks and store as `signhost` messages
- Added `role_visibility` JSON column — certain message types visible only to `admin` or `seller`
- Added filter params to `GET /api/chat/conversations`: `?type=signhost`, `?unread=true`, `?yacht_id=42`
- Added `GET /api/chat/conversations/{id}/ai-summary` — returns GPT-generated summary of the conversation

**API Example:**
```http
GET /api/chat/conversations/22/messages?type=signhost
Authorization: Bearer <token>

200 OK
{
  "data": [
    { "id": 301, "type": "signhost", "body": "Document signed by Jan de Vries at 2026-05-23T09:12:00Z", "visible_to": ["admin","seller"] }
  ]
}
```

```http
GET /api/chat/conversations/22/ai-summary
Authorization: Bearer <token>

200 OK
{ "summary": "Buyer Jan de Vries is interested in the Bayliner 2855 at €25,000. They requested a sea trial on Saturday. Signhost contract was sent and signed." }
```

---

### Card #272 — YachtShift Sync Layer
**Branch:** `feature/272-yachtshift-sync`

**What was done:**
- Created `YachtShiftSyncService` with `import()` and `export()` methods for Schepenkring
- Import: pulls listings from YachtShift API, maps fields via `BoatFieldMapping`, detects conflicts, writes audit log
- Export: pushes Schepenkring listings to YachtShift when status = `published`
- Added `POST /api/admin/yachtshift/sync` (manual trigger) and `GET /api/admin/yachtshift/sync/status`
- Conflict resolution: newest timestamp wins by default; admin can override via `POST /api/admin/yachtshift/conflicts/{id}/resolve`

**API Example:**
```http
POST /api/admin/yachtshift/sync
Authorization: Bearer <admin-token>
Content-Type: application/json

{ "direction": "import", "dry_run": false }

200 OK
{
  "imported": 12,
  "exported": 0,
  "conflicts": 2,
  "audit_log_ids": [1201, 1202, 1203]
}
```

---

### Card #273 — Fix AI Boat Library/Scraper
**Branch:** `feature/273-fix-ai-boat-scraper`

**What was done:**
- Fixed pagination bug in `BoatScraperService` that caused only ~1,600/3,100 boats to be scraped
- Added field validation against `BoatField` schema before inserting into Pinecone
- Added audit log entry per boat with confidence score and field coverage %
- Added `POST /api/admin/ai-library/re-index` to trigger full Pinecone re-index
- Added `GET /api/admin/ai-library/stats` to surface scraper health metrics

**API Example:**
```http
GET /api/admin/ai-library/stats
Authorization: Bearer <admin-token>

200 OK
{
  "total_boats_scraped": 3102,
  "pinecone_vectors": 3100,
  "avg_confidence": 0.84,
  "last_scrape_at": "2026-05-22T03:00:00Z",
  "fields_coverage": { "brand": 0.99, "model": 0.97, "year": 0.91, "length": 0.88 }
}
```

---

### Card #274 — Full Seller Onboarding Test (Bayliner 2855)
**Branch:** `feature/274-seller-onboarding-e2e-test`

**What was done:**
- Added `SellerOnboardingE2ETest` feature test covering the full Bayliner 2855 scenario
- Test covers: register → profile → KYC → submit → AI draft creation → image upload → publish
- Added `OnboardingSeeder` with a Bayliner 2855 fixture dataset for local testing

**API Example (test flow):**
```
POST /api/auth/register          → 201 (user created)
POST /api/seller-onboarding/start → 200
PUT  /api/seller-onboarding/profile → 200
POST /api/seller-onboarding/kyc/answers → 200
POST /api/seller-onboarding/submit → 200 { "status": "complete" }
POST /api/onboarding/ai-draft      → 201 { "draft_id": ... }
POST /api/yachts                   → 201 { "id": ... }
POST /api/yachts/{id}/images/upload → 200
PATCH /api/yachts/{id}             → 200 { "status": "published" }
```

---

### Card #275 — AI Helpdesk
**Branch:** `feature/275-ai-helpdesk`

**What was done:**
- Created `HelpdeskChannel` model with types: `phone | chat | sms | email`
- Integrated Vonage Voice with OpenAI Realtime API for live phone helpdesk (`POST /api/helpdesk/voice/session`)
- Chat and SMS channels route through existing `ChatConversationService` with `helpdesk` tag
- Added tag/event/action system: `POST /api/helpdesk/events` records call events; actions trigger automations
- Added `GET /api/helpdesk/sessions` and `GET /api/helpdesk/sessions/{id}/transcript`

**API Example:**
```http
POST /api/helpdesk/voice/session
Authorization: Bearer <token>
Content-Type: application/json

{ "phone_number": "+31612345678", "language": "nl" }

200 OK
{
  "session_id": "vs_abc123",
  "vonage_call_id": "ca_xyz789",
  "realtime_session_url": "wss://api.openai.com/v1/realtime?session=...",
  "status": "initiated"
}
```

---

### Card #276 — Location-Based Bidding Modes
**Branch:** `feature/276-location-based-bidding`

**What was done:**
- Added location settings columns: `seller_bid_notifications_enabled`, `direct_buyer_seller_chat_enabled`, `broker_controlled_bidding`, `bid_routing_mode` (`direct | broker`)
- `GET /api/public/locations/{id}/widget-settings` now returns all bid routing flags
- Bid placement logic checks `bid_routing_mode`: if `broker`, bids go to broker inbox; if `direct`, seller is notified directly
- Added `PUT /api/admin/locations/{id}/bid-settings` for admins to configure per-location bidding mode

**API Example:**
```http
PUT /api/admin/locations/3/bid-settings
Authorization: Bearer <admin-token>
Content-Type: application/json

{
  "seller_bid_notifications_enabled": true,
  "direct_buyer_seller_chat_enabled": false,
  "bid_routing_mode": "broker"
}

200 OK
{
  "location_id": 3,
  "seller_bid_notifications_enabled": true,
  "direct_buyer_seller_chat_enabled": false,
  "bid_routing_mode": "broker"
}
```

---

### Card #277 — Full Platform QA Checklist
**Branch:** `feature/277-platform-qa-checklist`

**What was done:**
- Created `docs/qa-checklist.md` with comprehensive test scenarios covering all platform features
- Checklist includes: auth, onboarding, bids, chat, contracts, AI features, YachtShift sync, helpdesk, dashboard, and admin panels
- Added `GET /api/admin/qa/health` endpoint that runs automated smoke-checks and returns a pass/fail report

**API Example:**
```http
GET /api/admin/qa/health
Authorization: Bearer <admin-token>

200 OK
{
  "checks": {
    "database": "ok",
    "pinecone": "ok",
    "signhost": "ok",
    "vonage": "ok",
    "yachtshift_api": "degraded"
  },
  "overall": "degraded",
  "checked_at": "2026-05-23T12:00:00Z"
}
```

---

### Card #281 — Client Contract Card Improvement
**Branch:** `feature/281-contract-card-improvement`

**What was done:**
- Added `yacht_id` relation to `SignDocument` / `sign_requests` table (migration)
- `GET /api/signhost/documents` now returns `yacht` object with `name`, `thumbnail_url`, `year`, `brand`
- Fixed Signhost signing URL bug: `yachtSignUrl()` now refreshes a new signing URL if the existing one has expired (Signhost URLs expire after 30 days)
- Added `POST /api/yachts/{yachtId}/signhost/refresh-url` to force a URL refresh

**API Example:**
```http
GET /api/signhost/documents
Authorization: Bearer <token>

200 OK
{
  "data": [
    {
      "id": 5,
      "status": "pending",
      "yacht": {
        "id": 42,
        "name": "Bayliner 2855",
        "year": 1997,
        "thumbnail_url": "https://cdn.schepenkring.nl/yachts/42/thumb.jpg"
      },
      "signing_url": "https://api.signhost.com/api/transaction/abc123/file/sign",
      "expires_at": "2026-06-22T00:00:00Z"
    }
  ]
}
```

---

### Card #148 — Co-pilot "Create Boat" Bug Fix
**Branch:** `fix/148-copilot-create-boat`

**What was done:**
- Identified root cause: `CopilotActionExecutionService` was not passing `yacht_id` context when creating a new boat via co-pilot, causing a null reference in the `CreateBoatAction` handler
- Fixed the context payload to include `location_id` and `user_id` from the authenticated session
- Added validation: if co-pilot `create_boat` action is missing required fields, return a clear error message instead of a 500
- Added `copilot/audit` log entry for each boat creation attempt

**API Example:**
```http
POST /api/copilot/resolve
Authorization: Bearer <token>
Content-Type: application/json

{ "message": "Create a new boat listing for a 2020 Bavaria 34 Cruiser" }

200 OK
{
  "action": "create_boat",
  "result": {
    "yacht_id": 88,
    "draft_id": 31,
    "message": "Boat draft created for Bavaria 34 Cruiser (2020). Ready for image upload."
  }
}
```
