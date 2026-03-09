# Boat Creation Workflow Technical Plan

## Objective

Improve the boat creation workflow to:

1. Prevent user data loss during language changes, refreshes, crashes, and intermittent connectivity.
2. Reduce perceived and actual image upload latency.
3. Make AI extraction non-blocking so users can continue working while enrichment runs.
4. Maintain responsive UX with clear save/progress states.

## Current State (Validated in Code)

### Frontend

- Main wizard page: `/src/app/[locale]/dashboard/[role]/yachts/[id]/page.tsx`
- Draft hook exists: `/src/hooks/useYachtDraft.ts`
- Image pipeline hook exists: `/src/hooks/useImagePipeline.ts`
- Language switcher exists: `/src/components/common/language-switcher.tsx`

Observed issues:

1. `useYachtDraft` is imported but not fully integrated into form lifecycle.
- Most fields use `defaultValue` and are not reliably persisted step-by-step.
2. AI extraction is executed inline in the page (`/ai/pipeline-extract`) with a blocking loading modal.
3. Auto-trigger extraction occurs immediately after image approval in new mode.
4. Language switch warning exists, but route matching misses some create paths and does not explicitly flush draft before navigation.

### Backend

- Image pipeline routes/controllers exist:
  - `/routes/api.php` under `yachts/{yachtId}/images/*`
  - `/app/Http/Controllers/Api/ImagePipelineController.php`
- Async image processing already exists:
  - `ProcessYachtImageJob`
  - `EnhanceYachtImageJob`
- AI pipeline currently exposed as synchronous request:
  - `POST /ai/pipeline-extract`
  - `/app/Http/Controllers/Api/AiPipelineController.php`
- Queue defaults to async-capable `database` connection in config.

Observed issue:

1. AI extraction pipeline is still synchronous from frontend perspective and blocks user flow.
2. Step unlock currently waits for processing and enhancement completion, which delays progression.

## Target Architecture

## A. Draft Persistence: Local-First + Server-Side Durable Draft

### High-level design

Use a two-layer draft system:

1. Local durable layer (fast):
- IndexedDB for large payloads and image references.
- localStorage for small metadata and active draft pointer.
2. Server durable layer (cross-device and recovery):
- `yacht_drafts` table.
- REST endpoints for create/read/patch/commit.

This provides instant saves and durable recovery after refresh/language switch/tab close.

### Data model: `yacht_drafts`

Proposed migration:

- `id` (UUID, primary key)
- `user_id` (FK)
- `yacht_id` (nullable FK, assigned once draft is backed by server yacht)
- `status` enum: `active`, `submitted`, `abandoned`
- `wizard_step` integer default `1`
- `payload_json` JSON (all form fields)
- `ui_state_json` JSON (step completion, selected language for text editor, etc.)
- `images_manifest_json` JSON (image IDs, local refs, sort, approval state)
- `ai_state_json` JSON (run id, last result summary, pending apply changes)
- `version` integer default `1` (optimistic locking)
- `last_client_saved_at` timestamp nullable
- timestamps + soft deletes

Indexes:

- (`user_id`, `status`, `updated_at`)
- (`yacht_id`)

### Draft API contract

1. `POST /api/yacht-drafts`
- Creates draft and returns full snapshot.

2. `GET /api/yacht-drafts/{draftId}`
- Returns latest server snapshot.

3. `PATCH /api/yacht-drafts/{draftId}`
- Patch payload:
  - `version`
  - `wizard_step` optional
  - `payload_patch` object optional
  - `ui_state_patch` object optional
  - `images_manifest_patch` object optional
  - `ai_state_patch` object optional
  - `client_saved_at` timestamp optional
- Returns updated draft and new `version`.
- On conflict (version mismatch), return `409` with server snapshot.

4. `POST /api/yacht-drafts/{draftId}/attach-yacht`
- Body: `yacht_id`
- Binds draft to created yacht.

5. `POST /api/yacht-drafts/{draftId}/commit`
- Marks draft submitted after successful yacht submission.

### Frontend draft manager behavior

Implement shared `draft-manager` service (new file under frontend `src/lib/`):

1. Debounced patch every 1000 ms on input changes.
2. Immediate patch on:
- step navigation
- image upload/approve/delete/toggle
- language switch click
- `visibilitychange=hidden`
- `beforeunload`
3. Snapshot fallback every 30s.
4. On startup:
- restore local draft immediately
- fetch server snapshot in background
- merge by timestamp and field-touch map
5. Conflict handling:
- if `409`, keep user-touched fields from local session
- notify user with non-blocking banner: "Recovered draft with minor conflicts."

## B. Language Change Safety

### Requirements

1. No data loss on language switch.
2. Keep same draft across locale route.

### Changes

1. Update language switcher logic:
- include `/yachts/new` route protection in unsaved check.
- perform `await flushDraft({ timeoutMs: 500 })` before `router.push`.

2. Preserve draft identity in URL:
- append `?draftId=<uuid>` during locale route transitions in wizard pages.

3. Keep draft payload locale-agnostic:
- keys remain canonical backend field names.
- localized labels remain presentational only.

## C. Image Upload Performance Optimization

## Phase 1 (quick wins, no infrastructure change)

1. Chunked concurrent uploads in frontend:
- split selected files into chunks of 3-5.
- send requests in parallel with bounded concurrency.
- retry failed chunks with exponential backoff.

2. Progressive UI:
- per-file progress states (`queued`, `uploading`, `uploaded`, `processing`, `ready`).
- optimistic placeholder cards added instantly.

3. Pre-upload client optimization rule:
- if image > threshold (example 8MB or > 4000px), downscale in web worker before upload.
- keep originals optional via existing toggle.

## Phase 2 (higher impact)

1. Direct-to-storage upload with presigned URLs:
- `POST /yachts/{id}/images/upload-session` -> returns upload targets.
- client uploads directly to object storage.
- `POST /yachts/{id}/images/finalize` to create DB records + dispatch jobs.

2. Duplicate detection:
- compute client hash (or server hash on finalize).
- skip duplicates for same yacht and return existing image references.

## Gate logic improvement

Current unlock requires approved images and no processing/enhancing.

Change to:

- Step 2 unlock when:
  - `approved_count >= min_required`
- Do not block on enhancement status.
- Continue showing enhancement progress badge per image.

## D. AI Extraction: Background Async Runs

### Goal

AI extraction must never block form progression.

### Data model: `yacht_ai_runs`

Proposed migration:

- `id` UUID
- `yacht_id` FK
- `draft_id` UUID nullable
- `status` enum: `queued`, `running`, `completed`, `failed`, `stale`, `cancelled`
- `trigger` enum: `auto_after_approve`, `manual`, `retry`
- `source_image_ids_json` JSON
- `hint_text` text nullable
- `result_json` JSON nullable (step2 values + meta)
- `error_text` text nullable
- `started_at`, `completed_at` nullable
- timestamps

Indexes:

- (`yacht_id`, `created_at`)
- (`status`, `created_at`)

### API contract

1. `POST /api/yachts/{yachtId}/ai-runs`
- Body: `draft_id`, `hint_text`, `trigger`
- Return `202`:
  - `run_id`
  - `status=queued`

2. `GET /api/ai-runs/{runId}`
- Return run status, progress, result summary.

3. `GET /api/yachts/{yachtId}/ai-runs/latest`
- Return latest non-stale run.

4. `POST /api/ai-runs/{runId}/apply`
- Applies result to draft/yacht only for fields not edited after run started.
- Returns applied/rejected field list.

### Backend execution

1. Create `RunYachtAiExtractionJob` implementing `ShouldQueue`.
2. Move AI pipeline orchestration from controller into service class:
- `YachtAiExtractionService`
- controller becomes thin enqueue/status layer.
3. Idempotency:
- fingerprint = `yacht_id + approved_image_ids + hint_hash`.
- if same fingerprint and completed recently, return existing run unless `force=true`.
4. Staleness:
- if new approved images appear while run is `queued/running`, mark old run `stale` and enqueue new run.

### Frontend UX

1. After image approval, enqueue run in background.
2. Allow immediate navigation to Step 2.
3. Show top banner:
- `AI analyzing images in background...`
- transitions to `AI suggestions ready` with button `Review and apply`.
4. Replace blocking modal with non-blocking status card.
5. Field merge policy:
- AI can auto-fill empty fields.
- edited fields require explicit user confirmation.

## E. Queue and Worker Topology

Define separate queues for isolation:

1. `images` for `ProcessYachtImageJob`
2. `image-enhance` for `EnhanceYachtImageJob`
3. `ai-extract` for `RunYachtAiExtractionJob`

Worker suggestions:

1. images:
- concurrency high (CPU-bound but short)
2. image-enhance:
- lower concurrency (external service latency)
3. ai-extract:
- low concurrency, strict retry/backoff

Operational requirements:

1. Ensure workers are always running in prod/staging.
2. Alert on queue depth and job age thresholds.
3. Add failed job dashboards by queue name.

## F. Frontend Implementation Tasks

### 1. Wizard state integration

File: `/src/app/[locale]/dashboard/[role]/yachts/[id]/page.tsx`

1. Replace current implicit uncontrolled autosave behavior with explicit draft sync:
- persist `selectedYacht`, `aiTexts`, `activeStep`, image manifest, `createdYachtId`, extraction state.

2. On mount:
- initialize from draft snapshot first.
- then hydrate from server yacht if edit mode and merge.

3. On step change:
- call `saveDraftNow()` before navigation.

4. On submit success:
- call draft commit endpoint and local cleanup.

### 2. Draft infrastructure

New files:

- `/src/lib/yacht-draft-manager.ts`
- `/src/lib/api/yacht-drafts.ts`

Responsibilities:

1. local cache read/write
2. debounced server patch
3. flush/retry/conflict resolution
4. migration helpers for old localStorage-only drafts

### 3. Language switcher hardening

File: `/src/components/common/language-switcher.tsx`

1. detect `/yachts/new` and `/yachts/{id}` consistently.
2. call shared `flushDraft()` before locale navigation.
3. preserve `draftId` query param.

### 4. Upload UX improvements

File: wizard page + image hook

1. chunked upload utility.
2. per-file state.
3. retry controls.

### 5. Non-blocking AI UX

1. remove auto-blocking extraction modal.
2. show background status and manual apply flow.

## G. Backend Implementation Tasks

### 1. Migrations

1. `create_yacht_drafts_table`
2. `create_yacht_ai_runs_table`

### 2. Models

1. `YachtDraft`
2. `YachtAiRun`

### 3. Controllers

1. `YachtDraftController`
2. `YachtAiRunController`

### 4. Services

1. `YachtDraftMergeService`
2. `YachtAiExtractionService` (refactor from `AiPipelineController`)

### 5. Jobs

1. `RunYachtAiExtractionJob`

### 6. Route additions

Under authenticated API group:

1. draft CRUD/patch/commit routes
2. AI run enqueue/status/apply routes

## H. Performance and UX Standards

Targets:

1. Draft local save p95 < 250ms
2. Draft server patch p95 < 1500ms
3. Image selection to visible placeholder < 100ms
4. Image upload to `processing` status p95 < 3s per file (normal broadband)
5. Zero blocking navigation due to AI extraction

UX standards:

1. Persistent save indicator:
- `Saving...`
- `Saved just now`
- `Saved at HH:MM`
2. Non-blocking toasts (avoid repeated noisy alerts).
3. Explicit offline banner with sync state and retry count.

## I. Testing Plan

## Unit tests

1. Draft patch merge logic (conflicts, version mismatch).
2. AI apply logic respects user-edited fields.
3. Upload chunk retry/backoff utilities.

## Integration tests

1. Create flow with refresh at each step restores correctly.
2. Language switch mid-edit keeps all fields/images.
3. Approve images then continue to Step 2 while AI run is pending.
4. AI run completes and apply button populates eligible fields only.
5. Offline edits sync when connection returns.

## E2E scenarios

1. New yacht full wizard with 20+ images.
2. Slow network + intermittent offline.
3. AI failure and retry path without blocking submit.
4. Concurrent tabs editing same draft (conflict handling).

## J. Rollout Plan

## Sprint 1

1. Local draft reliability hardening.
2. Language switch flush + route detection fixes.
3. Save indicator UX.

## Sprint 2

1. Server `yacht_drafts` APIs and migration.
2. Frontend server sync integration and conflict handling.

## Sprint 3

1. `yacht_ai_runs` + async extraction job.
2. Non-blocking AI UI and apply workflow.

## Sprint 4

1. Chunked upload + retry UX.
2. Step unlock decoupled from enhancement.
3. Optional direct-to-storage upload start.

## Sprint 5

1. Observability dashboards.
2. SLO alerts.
3. Performance tuning from production telemetry.

## K. Risks and Mitigations

1. Risk: uncontrolled form fields miss late updates.
- Mitigation: explicit snapshot extraction on every change + controlled fields for critical inputs.

2. Risk: draft conflicts from multi-tab editing.
- Mitigation: versioning + touched-field merge + user notification.

3. Risk: queue backlog delays AI run.
- Mitigation: dedicated `ai-extract` queue + autoscaling workers + stale run invalidation.

4. Risk: partial migration complexity.
- Mitigation: feature flags per capability:
  - `FEATURE_SERVER_DRAFTS`
  - `FEATURE_ASYNC_AI_RUNS`
  - `FEATURE_CHUNKED_UPLOAD`

## L. Definition of Done

1. No reproducible data loss via refresh, locale switch, or tab close.
2. User can proceed to Step 2 while AI extraction runs in background.
3. Upload UX shows progress and remains interactive.
4. Draft recovery works for both `new` and existing yacht edit flows.
5. Monitoring dashboards show draft save success rate, queue health, AI run outcomes.

