# Schepenkring Platform — QA Checklist

Use this checklist before every release or major deployment. Each section maps to a platform module. Run the automated health endpoint first:

```
GET /api/admin/qa/health
Authorization: Bearer <admin-token>
```

---

## 1. Auth & Registration

- [ ] Seller quick-register: `POST /api/onboarding/client/quick-register` returns token
- [ ] Standard registration: email + password, verification email sent
- [ ] Login returns Sanctum token
- [ ] Logout invalidates token (401 on next request)
- [ ] Password reset flow completes end-to-end

## 2. Seller Onboarding

- [ ] `GET /api/onboarding/seller/status` returns `steps.profile` and `steps.kyc`
- [ ] Profile step saves correctly (`PUT /api/onboarding/seller/profile`)
- [ ] KYC step accepts and persists documents
- [ ] Submit step transitions status to `submitted`
- [ ] AI draft: `POST /api/onboarding/client/ai-draft` parses free-text and returns `payload_json`
- [ ] Deeplink enforces Mon–Sat 09:00–18:00 Amsterdam window

## 3. Yacht Listings

- [ ] Create yacht draft via co-pilot
- [ ] AI autofill populates fields from reference boat
- [ ] Gallery upload (`POST /api/yachts/{id}/gallery`) returns image URLs
- [ ] Publish flow transitions yacht to `published` status
- [ ] YachtShift import (`POST /api/admin/yachtshift/sync` with `direction=import`) creates/updates yachts
- [ ] YachtShift export pushes published yachts

## 4. Bidding

- [ ] Register bidder (`POST /widget/{locationId}/bids/register`)
- [ ] Verify OTP (`POST /widget/{locationId}/bids/verify`)
- [ ] Place bid returns 201 with `bid`, `current_bid`, `minimum_next_bid`
- [ ] Outbid previous leader on new bid
- [ ] Location with `bids_page_enabled=false` returns 403 on place
- [ ] `GET /api/admin/locations/{id}/bid-settings` returns routing config
- [ ] `PUT /api/admin/locations/{id}/bid-settings` updates `bid_routing_mode`

## 5. Chat & Conversations

- [ ] New conversation created on bid acceptance
- [ ] `GET /api/conversations/{id}/messages` returns message list
- [ ] AI summary: `GET /api/conversations/{id}/ai-summary` returns 2–3 sentences
- [ ] `role_visibility` field restricts message visibility per role
- [ ] Public widget: `POST /public/conversations/{id}/messages` works without auth

## 6. Issue Tracker

- [ ] `POST /api/issues` creates issue and dispatches AI analysis job
- [ ] After queue processing, `ai_analysis` is populated on issue
- [ ] `POST /api/issues/{id}/retry-ai` re-queues the job
- [ ] Admin can list all issues; user sees only their own

## 7. Seller Dashboard

- [ ] `GET /api/dashboard/seller/summary` returns listings, bids, conversations, tasks, revenue
- [ ] Response is cached for 5 min (second request same data)
- [ ] Cache invalidated after new bid or listing change

## 8. AI Helpdesk

- [ ] `POST /api/helpdesk/voice/session` returns `session_id`, `vonage_call_id`, `openai_session_id`
- [ ] `POST /api/helpdesk/events` appends event to session
- [ ] `GET /api/helpdesk/sessions` lists sessions (scoped to user)
- [ ] `GET /api/helpdesk/sessions/{id}/transcript` returns transcript + events

## 9. Signhost / Contracts

- [ ] Document creation returns signing URL
- [ ] Webhook updates sign request status
- [ ] `GET /api/signhost/documents` returns yacht metadata alongside document list

## 10. Address Autocomplete

- [ ] `GET /api/places/autocomplete?q=...` returns NL-only suggestions
- [ ] Language is always `nl`

## 11. AI Library (Pinecone)

- [ ] `GET /api/admin/ai-library/stats` returns field coverage percentages
- [ ] `POST /api/admin/ai-library/reindex` upserts all yachts into Pinecone

## 12. Platform Health (automated)

Run `GET /api/admin/qa/health` and verify:

| Check | Expected |
|-------|----------|
| database | ok |
| cache | ok |
| queue | ok / warn |
| storage | ok |
| models | ok (non-zero counts) |
| openai_key | ok |
| vonage_key | ok |
| signhost_key | ok |
| pinecone_key | ok |

## 13. Security

- [ ] Unauthenticated requests to protected routes return 401
- [ ] Non-admin users cannot access `/api/admin/*` routes (403)
- [ ] Rate limiter triggers on excessive bid placement (429)
- [ ] SQL injection attempt on search returns safe error

## 14. Background Jobs

- [ ] Laravel Horizon (or queue worker) is running
- [ ] Failed job count is 0 in `failed_jobs` table
- [ ] `AnalyzeIssueWithAI` retries up to 3 times on failure

---

*Last updated: 2026-05-23*
