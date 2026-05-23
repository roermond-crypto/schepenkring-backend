# Schepenkring Backend — Trello Task Tracker

| # | Task Title | Branch | PR Link | Status | Requires Frontend |
|---|------------|--------|---------|--------|-------------------|
| 279 | BIDS Page Visibility (location settings) | `feature/279-bids-page-visibility` | [#68](https://github.com/roermond-crypto/schepenkring-backend/pull/68) | Done | Yes |
| 270 | Fix "Report an Issue" Modal (async AI job) | `feature/270-report-issue-async-ai` | [#69](https://github.com/roermond-crypto/schepenkring-backend/pull/69) | Done | No |
| 268 | Seller Dashboard Aggregated API | `feature/268-seller-dashboard-api` | [#70](https://github.com/roermond-crypto/schepenkring-backend/pull/70) | Done | Yes |
| 265 | Onboarding Corrections (remove payment/contract/verification) | `feature/265-onboarding-corrections` | [#71](https://github.com/roermond-crypto/schepenkring-backend/pull/71) | Done | Yes |
| 266 | Bidding System (counter offer, chat, deal creation) | `feature/266-bidding-system` | [#72](https://github.com/roermond-crypto/schepenkring-backend/pull/72) | Done | Yes |
| 271 | Selectable AI Boat Matches | `feature/271-selectable-ai-boat-matches` | [#73](https://github.com/roermond-crypto/schepenkring-backend/pull/73) | Done | Yes |
| 267 | Client Onboarding & AI Draft Boat Flow | `feature/267-client-onboarding-ai-flow` | [#74](https://github.com/roermond-crypto/schepenkring-backend/pull/74) | Done | Yes |
| 269 | Dashboard Chat Upgrade (message types, Signhost, filters) | `feature/269-dashboard-chat-upgrade` | [#75](https://github.com/roermond-crypto/schepenkring-backend/pull/75) | Done | Yes |
| 272 | YachtShift Sync Layer (two-way, audit, conflict) | `feature/272-yachtshift-sync` | [#76](https://github.com/roermond-crypto/schepenkring-backend/pull/76) | Done | No |
| 273 | Fix AI Boat Library/Scraper (Pinecone re-index) | `feature/273-fix-ai-boat-scraper` | [#77](https://github.com/roermond-crypto/schepenkring-backend/pull/77) | Done | No |
| 274 | Full Seller Onboarding Test (Bayliner 2855) | `feature/274-seller-onboarding-e2e-test` | [#78](https://github.com/roermond-crypto/schepenkring-backend/pull/78) | Done | No |
| 275 | AI Helpdesk (Vonage + OpenAI Realtime) | `feature/275-ai-helpdesk` | [#79](https://github.com/roermond-crypto/schepenkring-backend/pull/79) | Done | Yes |
| 276 | Location-Based Bidding Modes | `feature/276-location-based-bidding` | [#80](https://github.com/roermond-crypto/schepenkring-backend/pull/80) | Done | Yes |
| 277 | Full Platform QA Checklist | `feature/277-platform-qa-checklist` | [#81](https://github.com/roermond-crypto/schepenkring-backend/pull/81) | Done | No |
| 281 | Client Contract Card Improvement | `feature/281-contract-card-improvement` | [#82](https://github.com/roermond-crypto/schepenkring-backend/pull/82) | Done | Yes |
| 148 | Co-pilot "Create Boat" Bug Fix | `fix/148-copilot-create-boat` | [#83](https://github.com/roermond-crypto/schepenkring-backend/pull/83) | Done | No |

---

## Task Details

### Card #279 — BIDS Page Visibility
**Branch:** `feature/279-bids-page-visibility`

**What was done:**
- Added `bids_page_enabled`, `seller_bid_notifications_enabled`, `direct_buyer_seller_chat_enabled`, `bid_routing_mode` to `locations` table (migration)
- Updated `Location` model fillable + casts
- Updated `LocationWidgetSettingsController::show()` and `update()` to include bid settings

**API Example:**
```http
GET /api/admin/locations/1/widget-settings
Authorization: Bearer <admin-token>

200 OK
{
  "bids_page_enabled": true,
  "seller_bid_notifications_enabled": true,
  "direct_buyer_seller_chat_enabled": false,
  "bid_routing_mode": "direct"
}
```

---

### Card #270 — Fix "Report an Issue" Modal
**Branch:** `feature/270-report-issue-async-ai`

**What was done:**
- Created `Issue` model and `issues` table migration
- Created `AnalyzeIssueWithAI` queued job (GPT-4o-mini, 3 tries, 60s backoff)
- `POST /api/issues` returns `202 Accepted` immediately; AI analysis runs async
- `POST /api/admin/issues/{id}/retry-ai` re-dispatches the job

**API Example:**
```http
POST /api/issues
Authorization: Bearer <token>
Content-Type: application/json

{ "title": "Listing blank screen", "description": "Bayliner 2855 page shows nothing.", "yacht_id": 42 }

202 Accepted
{ "message": "Issue reported. AI analysis is running in the background.", "issue_id": 7 }
```

---

### Card #268 — Seller Dashboard Aggregated API
**Branch:** `feature/268-seller-dashboard-api`

**What was done:**
- `SellerDashboardService` aggregates: active listings, bids, conversations, tasks, revenue
- Response cached 5 min per user via `Cache::remember("seller_dashboard_{$user->id}", 300, ...)`
- `GET /api/dashboard/seller/summary` route added

**API Example:**
```http
GET /api/dashboard/seller/summary
Authorization: Bearer <token>

200 OK
{
  "listings": { "active": 3, "draft": 1, "sold": 12 },
  "bids": { "total_received": 47, "pending_review": 5 },
  "conversations": { "open": 8, "unread": 3 },
  "tasks": { "pending": 4, "overdue": 1 },
  "revenue": { "total_eur": 184500, "this_month_eur": 12000 }
}
```

---

### Card #265 — Onboarding Corrections
**Branch:** `feature/265-onboarding-corrections`

**What was done:**
- Removed iDIN/iDEAL/payment/contract gates from seller onboarding `submit()` and `formatStatus()`
- Flow is now profile → KYC → submit
- Google Places autocomplete restricted to NL (`includedRegionCodes: ['nl']`, `languageCode: 'nl'`)

**API Example:**
```http
GET /api/seller-onboarding/status
Authorization: Bearer <token>

200 OK
{
  "step": "kyc",
  "steps": { "profile": "complete", "kyc": "pending" }
}
```

---

### Card #266 — Bidding System
**Branch:** `feature/266-bidding-system`

**What was done:**
- `OwnerBid`, `Deal` models and migrations
- `OwnerBidController`: `place`, `accept`, `reject`, `counter`, `openBuyerSellerChat`
- `accept()` creates a `Deal` + `Conversation` (channel: `bid_deal`) + notifies buyer via `AppNotification::notify()`

**API Example:**
```http
POST /api/owner-bids/55/accept
Authorization: Bearer <seller-token>

200 OK
{ "deal_id": 12, "conversation_id": "uuid-here", "message": "Bid accepted." }
```

---

### Card #271 — Selectable AI Boat Matches
**Branch:** `feature/271-selectable-ai-boat-matches`

**What was done:**
- `POST /api/admin/yachts/draft/{id}/select-reference-boat` — stores `reference_yacht_id` in `ai_state_json`
- `POST /api/admin/yachts/draft/{id}/ai-autofill` — GPT-4o-mini fills draft fields from reference
- `GET /api/admin/yachts/draft/{id}/ai-matches` — returns Pinecone matches enriched with Yacht data

**API Example:**
```http
POST /api/admin/yachts/draft/18/ai-autofill
Authorization: Bearer <admin-token>

200 OK
{ "filled_fields": ["length", "beam", "year", "engine_brand"], "confidence": 0.91 }
```

---

### Card #267 — Client Onboarding & AI Draft Boat Flow
**Branch:** `feature/267-client-onboarding-ai-flow`

**What was done:**
- `POST /api/onboarding/client/quick-register` — name + email, returns Sanctum token
- `POST /api/onboarding/client/ai-draft` — parses free-text boat description, creates `YachtDraft`
- `POST /api/onboarding/client/deeplink` — signed URL, Mon–Sat 09:00–18:00 Amsterdam, cached 4h
- `GET /api/onboarding/client/thank-you` — user info + latest draft

**API Example:**
```http
POST /api/onboarding/client/ai-draft
Authorization: Bearer <token>
Content-Type: application/json

{ "description": "1997 Bayliner 2855, white, twin Mercruiser, GPS, VHF." }

201 Created
{ "draft_id": 29, "yacht": { "brand": "Bayliner", "model": "2855", "year": 1997 }, "confidence": 0.87 }
```

---

### Card #269 — Dashboard Chat Upgrade
**Branch:** `feature/269-dashboard-chat-upgrade`

**What was done:**
- `role_visibility` JSON column added to `messages` table
- `GET /api/chat/conversations/{id}/ai-summary` calls GPT-4o-mini on last 50 messages

**API Example:**
```http
GET /api/chat/conversations/22/ai-summary
Authorization: Bearer <token>

200 OK
{ "summary": "Buyer Jan de Vries offered €24,500. Seller countered at €27,000. Contract was sent and signed." }
```

---

### Card #272 — YachtShift Sync Layer
**Branch:** `feature/272-yachtshift-sync`

**What was done:**
- `YachtShiftSyncService`: `import(dryRun)` and `export(dryRun)` with conflict detection
- `POST /api/admin/yachtshift/sync` with `{ direction: import|export|both, dry_run }`
- `GET /api/admin/yachtshift/sync/status`

**API Example:**
```http
POST /api/admin/yachtshift/sync
Authorization: Bearer <admin-token>
Content-Type: application/json

{ "direction": "import", "dry_run": false }

200 OK
{ "imported": 12, "exported": 0, "conflicts": 2 }
```

---

### Card #273 — Fix AI Boat Library/Scraper
**Branch:** `feature/273-fix-ai-boat-scraper`

**What was done:**
- Fixed pagination: added fallback CSS selectors `a[href*="/boot/"]`, `a[href*="/boten/"]` when `a.botenloop` returns empty
- `GET /api/admin/ai-library/stats` — field coverage percentages
- `POST /api/admin/ai-library/reindex` — upserts all yachts into Pinecone

**API Example:**
```http
GET /api/admin/ai-library/stats
Authorization: Bearer <admin-token>

200 OK
{ "total": 3102, "pinecone_vectors": 3100, "fields_coverage": { "brand": 0.99, "year": 0.91 } }
```

---

### Card #274 — Full Seller Onboarding Test (Bayliner 2855)
**Branch:** `feature/274-seller-onboarding-e2e-test`

**What was done:**
- `SellerOnboardingE2ETest` feature test: register → profile → KYC → submit → AI draft
- Asserts `steps.profile` and `steps.kyc` present; asserts `payment_status` and `contract_status` absent

**Test flow:**
```
POST /api/auth/register → 201
PUT  /api/seller-onboarding/profile → 200
POST /api/seller-onboarding/kyc → 200
POST /api/seller-onboarding/submit → 200 { "status": "complete" }
```

---

### Card #275 — AI Helpdesk
**Branch:** `feature/275-ai-helpdesk`

**What was done:**
- `helpdesk_sessions` table: channel, phone_number, language, vonage_call_id, openai_session_id, status, tags (JSON), events (JSON), transcript
- `POST /api/helpdesk/voice/session` — initiates Vonage call + OpenAI Realtime session (graceful mock fallback)
- `POST /api/helpdesk/events` — appends event to session
- `GET /api/helpdesk/sessions` and `GET /api/helpdesk/sessions/{id}/transcript`

**API Example:**
```http
POST /api/helpdesk/voice/session
Authorization: Bearer <token>
Content-Type: application/json

{ "phone_number": "+31612345678", "language": "nl" }

200 OK
{
  "session_id": "vs_14",
  "vonage_call_id": "ca_abc123",
  "openai_session_id": "sess_xyz789",
  "status": "initiated"
}
```

---

### Card #276 — Location-Based Bidding Modes
**Branch:** `feature/276-location-based-bidding`

**What was done:**
- Migration adds `bids_page_enabled`, `seller_bid_notifications_enabled`, `direct_buyer_seller_chat_enabled`, `bid_routing_mode` to `locations`
- `LocationBidSettingsController`: `GET /api/admin/locations/{id}/bid-settings` + `PUT /api/admin/locations/{id}/bid-settings`
- `BidWidgetController::place()` returns 403 when `bids_page_enabled=false` for the requested location

**API Example:**
```http
PUT /api/admin/locations/3/bid-settings
Authorization: Bearer <admin-token>
Content-Type: application/json

{ "bid_routing_mode": "admin_review", "bids_page_enabled": true }

200 OK
{ "location_id": 3, "bid_routing_mode": "admin_review", "bids_page_enabled": true }
```

---

### Card #277 — Full Platform QA Checklist
**Branch:** `feature/277-platform-qa-checklist`

**What was done:**
- `docs/qa-checklist.md` — 14-section manual QA checklist (auth, bidding, chat, helpdesk, contracts, AI, security)
- `GET /api/admin/qa/health` — live smoke-checks: DB, cache, queue, storage, model counts, API keys

**API Example:**
```http
GET /api/admin/qa/health
Authorization: Bearer <admin-token>

200 OK
{
  "status": "healthy",
  "checks": {
    "database": { "status": "ok" },
    "cache": { "status": "ok" },
    "queue": { "status": "ok", "queue_size": 0 },
    "openai_key": { "status": "ok" }
  },
  "generated_at": "2026-05-23T12:00:00Z"
}
```

---

### Card #281 — Client Contract Card Improvement
**Branch:** `feature/281-contract-card-improvement`

**What was done:**
- `SignRequestResource` now exposes `yacht_id` and loads yacht name/type/year/price when `entity_type=Yacht`
- `yacht()` BelongsTo relation added to `SignRequest` model
- `GET /api/signhost/documents` response includes `yacht` metadata
- `POST /api/signhost/refresh-url` re-fetches signing URL from Signhost and persists it

**API Example:**
```http
GET /api/signhost/documents?sign_request_id=5
Authorization: Bearer <token>

200 OK
{
  "yacht": { "id": 42, "name": "Bayliner 2855", "year": 1997, "asking_price": 24500 },
  "documents": [{ "id": 5, "type": "contract", "file_url": "https://..." }]
}
```

```http
POST /api/signhost/refresh-url
Authorization: Bearer <token>
Content-Type: application/json

{ "yacht_id": 42 }

200 OK
{ "sign_request": { "id": 5, "sign_url": "https://api.signhost.com/...", "status": "pending" } }
```

---

### Card #148 — Co-pilot "Create Boat" Bug Fix
**Branch:** `fix/148-copilot-create-boat`

**What was done:**
- Root cause: `CopilotActionExecutionService::applyTemplate()` cast `null` payload values to `""` (empty string), producing broken deeplinks like `?type=` or `?type=Array`; unfilled `{param}` tokens also remained verbatim in the URL
- Fix: `applyTemplate()` now skips null/array values; `stripUnfilledQueryParams()` drops query params that still contain `{placeholder}` after substitution; `execute()` returns `unfilled_params` list so the frontend can prompt for missing values

**API Example:**
```http
POST /api/admin/copilot/execute
Authorization: Bearer <admin-token>
Content-Type: application/json

{ "validation_token": "eyJ..." }

200 OK
{
  "status": "executed",
  "action_id": "create.boat",
  "execution": {
    "execution_type": "deeplink",
    "deeplink": "/admin/yachts/new",
    "unfilled_params": []
  }
}
```
