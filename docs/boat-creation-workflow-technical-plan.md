# Boat Creation Workflow Technical Plan

## Objective

Improve the boat creation workflow to:

1. Prevent user data loss during language changes, refreshes, crashes, and intermittent connectivity.
2. Reduce perceived and actual image upload latency.
3. Make AI extraction non-blocking so users can continue working while enrichment runs.
4. Maintain responsive UX with clear save/progress states.
5. Replace the current hardcoded yacht form with a priority-based dynamic field system.
6. Normalize YachtShift, scraped, and future import values before they reach UI, DB, AI, or Pinecone.
7. Give admin a central control layer for field visibility, mapping, AI relevance, and boat-type specific behavior.

## Implementation Status (updated 2026-04-25)

### Completed in this pass

1. Partner/location route parity:
- frontend role normalization now accepts `partner` and treats it as equivalent to `location` for dashboard access.
- sidebar labels, task board permissions, and impersonation role mapping were updated for partner/location compatibility.

2. Yacht video settings API parity:
- `GET /api/yachts/{yachtId}/video-settings`
- `PUT /api/yachts/{yachtId}/video-settings`
- existing `BoatVideoSettingController` is now reachable from `routes/api.php`.

3. Seller intake and paid listing workflow:
- added `boat_intakes`, `boat_intake_payments`, `listing_workflows`, `listing_workflow_versions`, and `listing_workflow_reviews`.
- added seller intake API:
  - `POST /api/seller-intakes`
  - `GET /api/seller-intakes/{id}`
  - `PUT /api/seller-intakes/{id}`
  - `POST /api/seller-intakes/{id}/photos`
  - `POST /api/seller-intakes/{id}/payment/session`
  - `GET /api/seller-intakes/{id}/payment/status`
- added listing workflow API:
  - `GET /api/listing-workflows/{id}`
  - `GET /api/listing-workflows/{id}/preview`
  - `POST /api/listing-workflows/{id}/approve`
  - `POST /api/listing-workflows/{id}/request-changes`
- added admin listing workflow API:
  - `GET /api/admin/listing-workflows`
  - `GET /api/admin/listing-workflows/{id}`
  - `POST /api/admin/listing-workflows/{id}/start-ai`
  - `POST /api/admin/listing-workflows/{id}/mark-reviewed`
  - `POST /api/admin/listing-workflows/{id}/publish`
  - `POST /api/admin/listing-workflows/{id}/reject`
  - `POST /api/admin/listing-workflows/{id}/archive`
- added Mollie payment service/config and `POST /api/webhooks/mollie` handling for seller listing intake payments.
- payment success creates a draft yacht, attaches intake photos as approved yacht images, snapshots the workflow, and exposes preview/approval actions.

4. Seller intake support:
- added `src/lib/api/seller-intakes.ts`.
- added `/[locale]/dashboard/[role]/listing-workflows/[id]/preview`.

5. Old admin yacht wizard parity:
- `/[locale]/dashboard/[role]/yachts/new` is now handled by the same dynamic `[id]` wizard as `/yachts/{id}`, matching old-project's `id === "new"` flow.
- after a server draft yacht is created, the frontend redirects from `/yachts/new` to `/yachts/{id}?step={step}&draftFlow=1`.
- Step 1 autocomplete now uses the old `/api/autocomplete/types|brands|models` endpoints and the configured backend API client.
- Step 1 reference documents can be drag-reordered and persist through `POST /api/yachts/{yachtId}/documents/reorder`.
- Step 5 includes old-project Marktplaats/channel publishing controls for admins and sales-website selection cards for non-admin sellers.
- Step 6 passes seller details and a location-step navigation callback into Signhost, matching old contract flow behavior.

6. Marktplaats/channel publishing API parity:
- added `boat_channel_listings` and `boat_channel_logs`.
- added channel listing API:
  - `GET /api/yachts/{id}/channel-listings`
  - `PUT /api/yachts/{id}/channel-listings/{channel}`
  - `GET /api/yachts/{id}/channel-listings/{channel}/logs`
  - `POST /api/yachts/{id}/channel-listings/{channel}/retry`
  - `POST /api/yachts/{id}/channel-listings/{channel}/pause`
  - `POST /api/yachts/{id}/channel-listings/{channel}/remove`
  - `POST /api/yachts/{id}/channel-listings/{channel}/sync`

7. Build blockers found during validation:
- fixed existing yacht editor TypeScript mismatches for availability defaults, boat-match template id access, document upload handlers, and internal review status typing.

### Still missing or intentionally deferred

1. Full old-project seller verification/onboarding parity:
- the new project does not have old seller onboarding/profile tables.
- publish gating currently allows authenticated users; dedicated seller verification must still be rebuilt if required for production.

2. Hardened Mollie webhook security:
- seller listing payments reconcile through Mollie status fetch by payment id.
- full signature/idempotency parity from the old broader webhook stack is still outstanding.

3. Async AI extraction runs:
- `yacht_ai_runs`, queued `RunYachtAiExtractionJob`, status polling, stale-run handling, and apply/merge endpoints remain missing.
- current AI extraction is still synchronous from the frontend perspective.

4. Full dynamic yacht form renderer:
- backend field config and admin field settings exist, but the large yacht editor is not fully replaced by a config-driven renderer.

5. Draft hardening:
- server yacht draft endpoints exist, but the frontend wizard still needs full local-first draft manager integration, flush-on-language-switch, conflict handling, and save indicators.

6. Upload performance improvements:
- chunked upload, direct-to-storage upload sessions, retry UI, and client-side pre-upload downscaling are still missing.

7. Public marketplace parity:
- public `/yachts`, yacht detail, brochure/compare/favorites, public inquiry, and per-yacht public booking flows from the old project are not yet ported.

8. Full Marktplaats external integration:
- Step 5 and local channel listing state are ported.
- actual Admarkt publishing jobs/feed builder/status polling from old-project are not fully ported yet; current new-project channel actions update local listing state and logs.

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
5. The yacht editor still hardcodes field grouping, ordering, and visibility inside one large page component instead of rendering from a central field registry.

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
3. YachtShift import maps source data directly into yacht columns and `saveSubTables()` without a reusable field-mapping control layer.
4. The yacht model flattens 228+ fields for API compatibility, but there is no field metadata model for step, block, priority, boat type, or AI relevance.

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

## E. Central Field Registry, Priority System, and Mapping Hub

### Goal

Create one admin-managed field system that controls:

1. Which fields exist and how they are labeled.
2. Which step and block each field belongs to.
3. Whether a field is `primary` or `secondary` per boat type.
4. How external source fields and raw values normalize into internal values.
5. Which normalized fields AI is allowed to use.

This becomes the control layer between:

1. frontend form rendering
2. backend storage
3. YachtShift import
4. scraped imports
5. AI extraction and generation
6. Pinecone-ready normalized payloads

### Non-negotiable rules

1. Each field has only two priorities:
- `primary`
- `secondary`
2. `primary` fields are always visible in their block.
3. `secondary` fields are hidden behind `+ Show more (X)`.
4. If any secondary field in a block has a value, that block auto-expands.
5. Priority is per boat type, not global.
6. Raw external values must never be used directly after import.
7. The data flow is always:
- `external key/value -> normalized value -> internal field`
8. AI receives normalized internal values only.
- primary fields are always included
- secondary fields are included only when filled

### Backend data model

#### 1. `boat_fields`

Represents the canonical internal field registry.

- `id`
- `internal_key` unique
- `labels_json` (`nl`, `en`, `de`, optional `fr`)
- `field_type`
- `block_key`
- `step_key`
- `sort_order`
- `storage_relation` nullable
- `storage_column`
- `ai_relevance` boolean default `true`
- `is_active` boolean default `true`
- timestamps

Notes:

1. `storage_relation` maps to the current flattened yacht backend model.
- Example: `accommodation`, `engine`, `comfort`
2. `storage_column` maps to the actual persisted column.
- Example: `cabins`, `berths`, `fuel`, `air_conditioning`
3. This allows the new field system to sit on top of the existing `Yacht::SUB_TABLE_MAP` instead of forcing an immediate schema rewrite.

#### 2. `boat_field_priorities`

Stores per-boat-type visibility rules.

- `id`
- `field_id`
- `boat_type_key`
- `priority` enum: `primary`, `secondary`
- timestamps

Notes:

1. `boat_type_key` should be a normalized internal slug such as `motorboat`, `sailboat`, `rib`, `tender`.
2. Do not depend directly on raw YachtShift `boat_type` strings for visibility logic.

#### 3. `boat_field_mappings`

Stores mapping rules from external systems into normalized internal values.

- `id`
- `field_id`
- `source` enum: `yachtshift`, `scrape`, `future_import`
- `external_key` nullable
- `external_value`
- `normalized_value`
- `match_type` enum: `exact`, `contains`, `regex`, `manual`
- timestamps

Notes:

1. This is value normalization, not only field-name mapping.
2. Multiple external values may point to the same normalized value.

#### 4. `boat_field_value_observations`

Stores discovered raw values and usage frequency for admin review.

- `id`
- `field_id`
- `source`
- `external_value`
- `observed_count`
- `last_seen_at`
- timestamps

This supports the “3000 boats” usage-stat workflow and lets admin promote or demote fields based on actual data coverage.

### Admin settings UI

#### Left panel

1. Fields grouped by block:
- dimensions
- construction
- accommodation / interior
- engine
- comfort
- deck equipment
- navigation
- safety
- rigging
2. Search by internal key or label.
3. Show coverage badge per field:
- example `Scrape 78%`

#### Right panel for selected field

##### Internal config

- internal key
- labels (`nl`, `en`, `de`)
- field type
- block
- step
- sort order
- storage relation
- storage column
- AI relevance flag

##### Priority config

- per-boat-type priority:
  - `primary`
  - `secondary`

##### Mapping config

1. YachtShift:
- external key selection
- value mapping table
2. Scraped values:
- all observed raw values
- frequency count
- editable normalized value
- unresolved value marker

### Frontend form-rendering contract

Add a backend endpoint that returns form config already filtered for boat type and step.

Suggested endpoint:

- `GET /api/boat-form-config?boat_type=<boatTypeKey>&step=<stepKey>`

Suggested response shape:

- `blocks[]`
  - `block_key`
  - `label`
  - `primary_fields[]`
  - `secondary_fields[]`
  - `secondary_count`

Rendering algorithm per block:

1. `fields = block fields filtered by boat_type_key`
2. `primaryFields = fields where priority = primary`
3. `secondaryFields = fields where priority = secondary`
4. render `primaryFields` immediately
5. if `secondaryFields.length > 0`, show `+ Show more (X)`
6. auto-expand if any `secondaryField` already has a value
7. toggle to `- Show less` when expanded

UX requirements:

1. Smooth expand/collapse animation.
2. Two-column compact layout on desktop where possible.
3. Optional completion badge per block:
- example `Interior 4/7 completed`

### Normalization and persistence flow

#### Import flow

1. identify external source field
2. resolve matching `boat_fields.internal_key`
3. normalize raw value through `boat_field_mappings`
4. persist normalized value into the mapped yacht column
5. log raw value into `boat_field_value_observations` when unknown or newly seen

#### Draft and form flow

1. drafts store canonical internal keys only
2. frontend renders from field config, not hardcoded JSX groups
3. values in form state remain normalized internal values

#### AI flow

1. AI extraction may propose values, but application into yacht/draft must target canonical internal keys
2. generation payloads must use normalized internal values only
3. unresolved raw values should be excluded or flagged instead of silently passed through

### Integration with existing code

This design should extend current structures instead of replacing them immediately:

1. Keep `Yacht::SUB_TABLE_MAP` as the physical storage map for now.
2. Add field metadata above it using `storage_relation` and `storage_column`.
3. Refactor the yacht editor page to fetch config and render blocks dynamically.
4. Refactor YachtShift and scraper import services to call a shared normalization service before `saveSubTables()`.
5. Keep API responses backward-compatible while gradually introducing config-driven rendering.

### Implementation phases

#### Phase 1

1. Introduce `boat_fields` and `boat_field_priorities`.
2. Build config endpoint for one step, starting with accommodation/interior.
3. Replace the hardcoded accommodation section in the yacht editor with a config-driven renderer.

#### Phase 2

1. Introduce `boat_field_mappings` and `boat_field_value_observations`.
2. Route YachtShift import through normalization.
3. Route scraped import through the same normalization path.

#### Phase 3

1. Build admin settings UI for field config and mapping review.
2. Show usage stats from observed values.
3. Apply AI relevance filtering from field settings into generation payloads.

## F. Queue and Worker Topology

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

## G. Frontend Implementation Tasks

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

### 2. Config-driven field renderer

Files:

- `/src/app/[locale]/dashboard/[role]/yachts/[id]/page.tsx`
- new shared renderer components under `/src/components/yachts/`

Responsibilities:

1. replace hardcoded field blocks with config-driven block rendering
2. support `primary` vs `secondary` visibility only
3. implement `+ Show more (X)` and `- Show less`
4. auto-expand blocks with filled secondary values
5. keep locale-specific labels purely presentational

### 3. Draft infrastructure

New files:

- `/src/lib/yacht-draft-manager.ts`
- `/src/lib/api/yacht-drafts.ts`

Responsibilities:

1. local cache read/write
2. debounced server patch
3. flush/retry/conflict resolution
4. migration helpers for old localStorage-only drafts

### 4. Admin settings UI

New page(s):

- admin field settings page in frontend dashboard

Responsibilities:

1. list fields by block
2. edit internal metadata
3. edit per-boat-type priority
4. review and correct YachtShift mappings
5. review and correct scraped value mappings with frequency counts

### 5. Language switcher hardening

File: `/src/components/common/language-switcher.tsx`

1. detect `/yachts/new` and `/yachts/{id}` consistently.
2. call shared `flushDraft()` before locale navigation.
3. preserve `draftId` query param.

### 6. Upload UX improvements

File: wizard page + image hook

1. chunked upload utility.
2. per-file state.
3. retry controls.

### 7. Non-blocking AI UX

1. remove auto-blocking extraction modal.
2. show background status and manual apply flow.

## H. Backend Implementation Tasks

### 1. Migrations

1. `create_yacht_drafts_table`
2. `create_yacht_ai_runs_table`
3. `create_boat_fields_table`
4. `create_boat_field_priorities_table`
5. `create_boat_field_mappings_table`
6. `create_boat_field_value_observations_table`

### 2. Models

1. `YachtDraft`
2. `YachtAiRun`
3. `BoatField`
4. `BoatFieldPriority`
5. `BoatFieldMapping`
6. `BoatFieldValueObservation`

### 3. Controllers

1. `YachtDraftController`
2. `YachtAiRunController`
3. `Admin/BoatFieldController`
4. `Admin/BoatFieldMappingController`
5. `BoatFormConfigController`

### 4. Services

1. `YachtDraftMergeService`
2. `YachtAiExtractionService` (refactor from `AiPipelineController`)
3. `BoatFieldConfigService`
4. `BoatFieldNormalizationService`
5. `BoatFieldObservationService`

### 5. Jobs

1. `RunYachtAiExtractionJob`
2. optional usage-stat aggregation job if observation growth becomes large

### 6. Route additions

Under authenticated API group:

1. draft CRUD/patch/commit routes
2. AI run enqueue/status/apply routes
3. admin field config CRUD routes
4. admin mapping review/update routes
5. boat-form-config read endpoint for frontend rendering

### 7. Import refactors

1. update YachtShift import to normalize field keys and values before save
2. update scraper import path to use the same normalization service
3. reject or flag unmapped raw values instead of silently persisting source-specific values

## I. Performance and UX Standards

Targets:

1. Draft local save p95 < 250ms
2. Draft server patch p95 < 1500ms
3. Image selection to visible placeholder < 100ms
4. Image upload to `processing` status p95 < 3s per file (normal broadband)
5. Zero blocking navigation due to AI extraction
6. Form blocks render with config response p95 < 500ms
7. No raw external values reach AI payloads in normal flow

UX standards:

1. Persistent save indicator:
- `Saving...`
- `Saved just now`
- `Saved at HH:MM`
2. Non-blocking toasts (avoid repeated noisy alerts).
3. Explicit offline banner with sync state and retry count.
4. Consistent `Show more` / `Show less` affordance across all configurable blocks.

## J. Testing Plan

## Unit tests

1. Draft patch merge logic (conflicts, version mismatch).
2. AI apply logic respects user-edited fields.
3. Upload chunk retry/backoff utilities.
4. field-priority resolution by boat type.
5. normalization service resolves mapped values correctly and flags unknown values.

## Integration tests

1. Create flow with refresh at each step restores correctly.
2. Language switch mid-edit keeps all fields/images.
3. Approve images then continue to Step 2 while AI run is pending.
4. AI run completes and apply button populates eligible fields only.
5. Offline edits sync when connection returns.
6. motorboat and sailboat render different primary/secondary field sets for the same block.
7. imported YachtShift and scraped values normalize to the same internal value for the same field.

## E2E scenarios

1. New yacht full wizard with 20+ images.
2. Slow network + intermittent offline.
3. AI failure and retry path without blocking submit.
4. Concurrent tabs editing same draft (conflict handling).
5. admin changes field priority and frontend reflects it without code changes.
6. block auto-expands when a secondary field already has a value.

## K. Rollout Plan

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

1. `boat_fields` and `boat_field_priorities` foundations.
2. Config-driven renderer for one target block (`accommodation` / `interior`).
3. Step unlock decoupled from enhancement.

## Sprint 5

1. `boat_field_mappings` and `boat_field_value_observations`.
2. YachtShift and scrape normalization integration.
3. Admin mapping review UI.

## Sprint 6

1. Observability dashboards.
2. SLO alerts.
3. Performance tuning from production telemetry.
4. Expand config-driven renderer to remaining yacht blocks.

## L. Risks and Mitigations

1. Risk: uncontrolled form fields miss late updates.
- Mitigation: explicit snapshot extraction on every change + controlled fields for critical inputs.

2. Risk: draft conflicts from multi-tab editing.
- Mitigation: versioning + touched-field merge + user notification.

3. Risk: queue backlog delays AI run.
- Mitigation: dedicated `ai-extract` queue + autoscaling workers + stale run invalidation.

4. Risk: field registry diverges from actual yacht storage columns.
- Mitigation: store `storage_relation` and `storage_column`, validate them against `Yacht::SUB_TABLE_MAP`, and block invalid admin saves.

5. Risk: unmapped raw source values silently pollute normalized data.
- Mitigation: log unknown values to observation table, require explicit mapping for reuse, and avoid sending unresolved values to AI.

6. Risk: partial migration complexity.
- Mitigation: feature flags per capability:
  - `FEATURE_SERVER_DRAFTS`
  - `FEATURE_ASYNC_AI_RUNS`
  - `FEATURE_DYNAMIC_BOAT_FIELDS`
  - `FEATURE_BOAT_FIELD_MAPPINGS`
  - `FEATURE_CHUNKED_UPLOAD`

## M. Definition of Done

1. No reproducible data loss via refresh, locale switch, or tab close.
2. User can proceed to Step 2 while AI extraction runs in background.
3. Upload UX shows progress and remains interactive.
4. Draft recovery works for both `new` and existing yacht edit flows.
5. Monitoring dashboards show draft save success rate, queue health, AI run outcomes.
6. At least one target block in the yacht form is rendered entirely from backend field config.
7. Admin can set `primary` or `secondary` priority per boat type without code changes.
8. YachtShift and scraped imports normalize into the same internal values for mapped fields.
9. AI payload generation uses normalized internal values only.
