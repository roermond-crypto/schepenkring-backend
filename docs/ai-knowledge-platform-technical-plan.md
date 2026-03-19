# NauticSecure AI Knowledge Platform Technical Plan

## Objective

Turn NauticSecure from a FAQ-backed assistant into a platform-wide AI knowledge system that can answer questions about:

1. Boats and their specifications, risks, and status.
2. Harbors, services, hazards, and location context.
3. Insurance rules, claims guidance, and safety policy.
4. Uploaded knowledge documents such as manuals, coverage terms, and regulations.
5. Support conversations and recurring unanswered questions.

The goal is not just "chat with vectors", but a governed knowledge layer that combines:

- relational platform data
- ingested documents
- scraped harbor and regulation data
- Pinecone retrieval
- OpenAI reasoning
- admin review and auditability

## Current State (Validated In Code)

The codebase already has a meaningful foundation for this.

### Backend knowledge and AI pieces already present

- Pinecone-backed memory exists in `app/Services/CopilotMemoryService.php`.
- FAQ semantic retrieval exists in `app/Services/CopilotFaqService.php`.
- Chat context assembly exists in `app/Services/ChatAiContextService.php`.
- OpenAI/Gemini reply generation exists in `app/Services/ChatAiReplyService.php`.
- Admin-facing knowledge review exists in `app/Services/KnowledgeBrainService.php`.
- Knowledge Brain API exists in `app/Http/Controllers/Api/KnowledgeBrainController.php`.
- Document upload and FAQ extraction already exist in:
  - `app/Services/FaqKnowledgeIngestionService.php`
  - `app/Http/Controllers/Api/FaqKnowledgeController.php`
- FAQ training and Pinecone sync already exist in `app/Services/FaqTrainingService.php`.
- Pinecone/OpenAI config is already wired in `config/services.php`.
- A frontend admin page already exists at `src/app/[locale]/dashboard/[role]/knowledge-brain/page.tsx`.

### Important conclusion

NauticSecure does **not** need a brand-new AI subsystem from scratch.

The correct move is to evolve the current FAQ-centric stack into a **generic knowledge platform**.

## Key Recommendation

Do **not** start with a separate graph database.

For this product, the best first production architecture is:

1. relational database as source of truth
2. Pinecone for semantic retrieval
3. OpenAI for embeddings and reasoning
4. a "knowledge graph layer" represented in relational tables and metadata

This gives you the benefits of graph-style reasoning without the operational complexity of introducing Neo4j or another graph engine too early.

In practice, the "graph" should first be modeled as:

- entities
- typed relationships
- knowledge chunks
- vectorized retrieval records

Later, if query complexity justifies it, a dedicated graph engine can be added behind the same service boundary.

## Target Architecture

### A. Core knowledge domains

The platform-wide knowledge model should support these entity families:

- `boat`
- `harbor`
- `insurance_policy`
- `claim_rule`
- `safety_rule`
- `faq`
- `document`
- `weather_risk`
- `navigation_rule`
- `conversation_pattern`

### B. Knowledge graph layer

Create a normalized graph layer in the application database.

#### Proposed tables

1. `knowledge_entities`
- `id`
- `type` enum: `boat`, `harbor`, `policy`, `claim_rule`, `safety_rule`, `faq`, `document`, `weather_risk`, `navigation_rule`
- `source_table`
- `source_id`
- `location_id` nullable
- `title`
- `summary`
- `language`
- `status`
- `metadata_json`
- timestamps

2. `knowledge_relationships`
- `id`
- `from_entity_id`
- `to_entity_id`
- `relationship_type`
- `weight` nullable
- `metadata_json`
- timestamps

Relationship examples:

- `boat -> insured_by -> policy`
- `boat -> located_at -> harbor`
- `harbor -> has_hazard -> weather_risk`
- `policy -> excludes -> claim_rule`
- `document -> describes -> safety_rule`

3. `knowledge_documents`
- generalize the current `faq_knowledge_documents`
- uploaded PDFs, DOCX, XLSX, CSV, markdown
- scraped source dumps
- generated summaries

4. `knowledge_chunks`
- generalize the current `faq_knowledge_items`
- each chunk stores:
  - extracted text
  - generated question variants
  - summary
  - source entity/document link
  - review state
  - visibility
  - language
  - metadata

5. `knowledge_embeddings`
- optional local tracking table, even if Pinecone stores vectors
- useful for audit and retry workflows
- fields:
  - `chunk_id`
  - `pinecone_id`
  - `namespace`
  - `embedding_model`
  - `indexed_at`
  - `status`
  - `error_text`

6. `knowledge_ingestion_runs`
- track ingestion/import jobs
- source type
- status
- counts
- failures
- started/completed timestamps

### C. Pinecone retrieval model

Keep Pinecone as retrieval infrastructure, but stop thinking of it as FAQ-only memory.

Each vector should include metadata such as:

- `kind`
- `entity_type`
- `entity_id`
- `document_id`
- `chunk_id`
- `location_id`
- `language`
- `visibility`
- `brand`
- `model`
- `policy_type`
- `harbor_region`
- `risk_tags`
- `source_type`

Example `kind` values:

- `faq`
- `harbor`
- `boat_profile`
- `policy_rule`
- `claim_rule`
- `safety_rule`
- `navigation_rule`
- `document_chunk`
- `weather_guidance`

### D. Reasoning layer

Split retrieval and reasoning clearly.

1. Retrieval
- semantic search in Pinecone
- relational/entity lookup in MySQL
- filter by user, role, location, language, visibility

2. Reasoning
- OpenAI `responses` API or structured `chat/completions`
- combine:
  - user question
  - retrieved chunks
  - related entities
  - user/account context
  - current platform state

3. Answer assembly
- short answer
- confidence score
- citations/source list
- follow-up suggestions
- handoff recommendation when confidence is low

## How This Fits The Current Codebase

### 1. Evolve `CopilotMemoryService` into a generic vector store service

Current state:

- `CopilotMemoryService` stores FAQ/action/audit vectors.

Recommended change:

- introduce `KnowledgeVectorStoreService`
- keep `CopilotMemoryService` as a thin compatibility wrapper or deprecate it
- support generic upsert/search/delete by `kind` and entity metadata

### 2. Evolve `CopilotFaqService` into `KnowledgeRetrievalService`

Current state:

- `CopilotFaqService` retrieves FAQ answers using Pinecone + DB fallback.

Recommended change:

- preserve FAQ retrieval
- add support for boats, harbors, policies, claims, and safety documents
- return a normalized retrieval result:
  - `chunks`
  - `entities`
  - `relationships`
  - `strategy`
  - `confidence`

### 3. Extend `ChatAiContextService`

Current state:

- it already adds conversation, location, yacht, and FAQ context.

Recommended change:

- replace the FAQ-only knowledge payload with a generalized knowledge payload:
  - `knowledge.top_chunks`
  - `knowledge.related_entities`
  - `knowledge.risk_signals`
  - `knowledge.citations`
  - `knowledge.answer_strategy`

### 4. Keep `KnowledgeBrainService` as the admin governance layer

This service already gives you an admin review surface.

Recommended change:

- expand it beyond FAQ quality review into:
  - ingestion monitoring
  - embedding failures
  - duplicate chunk detection
  - stale policy/regulation warnings
  - suggested question expansions
  - unsafe/low-confidence answer audits

### 5. Reuse and expand `FaqKnowledgeIngestionService`

Current state:

- it extracts text, chunks it, generates Q&A, and creates reviewable items.

Recommended change:

- rename or generalize toward `KnowledgeIngestionService`
- support source types:
  - harbor scrape
  - policy PDF
  - claim handbook
  - safety manual
  - boating regulation
  - boat listing content
  - support transcript

## Unified Data Pipeline

Every knowledge source should pass through the same ingestion lifecycle.

### Pipeline

1. Source collection
- DB records
- uploaded documents
- scrapers
- support conversations

2. Normalize
- map fields to a canonical schema
- strip boilerplate
- extract metadata

3. Chunk
- semantic chunks, not arbitrary line splits only
- preserve source references

4. Expand
- generate paraphrases and alternate question forms
- generate summaries
- generate tags and risk markers

5. Embed
- use OpenAI embeddings

6. Upsert
- Pinecone vector storage
- relational chunk/entity tracking

7. Review
- admin review where needed

### Sources to index first

Phase 1:

- FAQs
- uploaded policy/manual documents
- harbor/location records
- yacht profiles

Phase 2:

- support ticket patterns
- conversation thumbs-up / accepted answers
- scraped boating regulations
- claims guidance content

## Question Expansion Strategy

This is one of the highest-leverage improvements.

For each approved knowledge chunk, store:

- canonical question
- alternate phrasings
- short answer
- long answer
- examples
- risk terms

Example:

Canonical:
- `Does insurance cover theft?`

Expansions:
- `Is my boat insured against theft?`
- `What happens if my yacht gets stolen?`
- `Does marine insurance cover stolen engines?`
- `Is theft included in coverage?`

### Proposed implementation

Add fields to `knowledge_chunks`:

- `canonical_question`
- `question_variants_json`
- `short_answer`
- `long_answer`
- `examples_json`
- `risk_tags_json`

Use OpenAI to generate expansions, but keep them reviewable for sensitive insurance/safety content.

## Smart Answer Generation (RAG)

### Target flow

1. user asks a question
2. retrieve top chunks from Pinecone
3. load related entities from relational DB
4. add user/account/location context
5. ask OpenAI to answer with structured output
6. return:
   - answer
   - confidence
   - sources
   - follow-up actions

### Important rule

For insurance, claims, and safety:

- never answer from the model alone
- require at least one approved source or explicitly respond with uncertainty and handoff

### Suggested response schema

- `answer`
- `confidence`
- `source_ids`
- `entity_ids`
- `recommended_actions`
- `requires_human_review`
- `reasoning_notes_internal`

## Harbor Intelligence System

NauticSecure already has location/harbor concepts. This should become a first-class AI domain.

### Harbor knowledge entity should include

- name
- city
- region
- coordinates
- depth
- services
- hazards
- storm exposure
- winter storage
- maintenance availability
- fuel availability
- regulations

### Retrieval examples

- `Find a safe harbor near Amsterdam for a 12m yacht`
- `Which harbor near Lelystad has winter storage and repair services?`
- `Which harbors are riskier during storms?`

### Recommended ingestion path

1. normalize `locations`
2. create derived harbor summaries
3. ingest scraper output as `knowledge_documents`
4. create harbor chunks and vectors

## Insurance and Claims Knowledge

This is where governance matters most.

### Recommended model

Do not store insurance content only as freeform vectors.

Store both:

1. structured policy and claims rules in relational tables
2. unstructured explanatory text in chunked documents

### Proposed tables

- `insurance_policies`
- `insurance_policy_rules`
- `claim_types`
- `claim_requirements`
- `safety_advisories`

Then create corresponding `knowledge_entities` and `knowledge_chunks`.

This lets AI answer:

- "Does theft coverage apply?"
- "What documents are needed for a storm claim?"
- "Can I stay insured in this harbor during a red weather warning?"

## Safety Assistant

The safety assistant should be built as a rule-backed AI layer, not pure chat.

### Inputs

- harbor/location
- boat type and dimensions
- weather alert data
- policy coverage rules
- safety advisories

### Outputs

- risk summary
- recommended action list
- related insurance reminder
- escalation trigger

Example:

- `Storm warning near your harbor. Check mooring lines, verify winter cover status, and confirm storm-damage coverage terms.`

## Admin Platform Extensions

The existing Knowledge Brain UI is the right home for this.

### Add these admin capabilities

1. upload source documents
2. ingest harbor scrape results
3. ingest regulation text
4. rebuild vectors by source type
5. inspect failed embeddings
6. approve question expansions
7. audit AI answers and source traces
8. compare low-confidence answers against trusted sources

### Suggested API additions

- `POST /api/knowledge/documents`
- `POST /api/knowledge/import/harbors`
- `POST /api/knowledge/import/policies`
- `POST /api/knowledge/reindex`
- `GET /api/knowledge/chunks`
- `GET /api/knowledge/entities`
- `POST /api/knowledge/answer`
- `GET /api/knowledge/answer-audits`

## Proposed Service Layer

Add or evolve toward these services:

### Ingestion

- `KnowledgeIngestionService`
- `KnowledgeChunkingService`
- `KnowledgeExpansionService`
- `KnowledgeEmbeddingService`

### Retrieval

- `KnowledgeVectorStoreService`
- `KnowledgeRetrievalService`
- `KnowledgeEntityGraphService`

### Answering

- `KnowledgeAnswerService`
- `KnowledgeSafetyAdvisorService`
- `KnowledgeCitationFormatter`

### Governance

- `KnowledgeAuditService`
- `KnowledgeBrainService` expanded

## Phased Rollout

## Phase 1: Generalize the existing FAQ knowledge stack

Goal:

- stop being FAQ-only
- keep the current system working

Changes:

1. Introduce generic knowledge enums and metadata.
2. Add `knowledge_entities`, `knowledge_relationships`, `knowledge_ingestion_runs`.
3. Add generic vector service abstraction.
4. Make `CopilotFaqService` call into `KnowledgeRetrievalService`.
5. Extend Knowledge Brain dashboard counters for generic knowledge types.

## Phase 2: Bring harbors and boats into the knowledge system

Goal:

- let AI answer real boating questions using structured platform data

Changes:

1. Create harbor entity/chunk generation jobs.
2. Create boat profile summary generation jobs.
3. Index harbor and boat knowledge into Pinecone.
4. Add retrieval filters by:
   - location
   - boat type
   - harbor region
   - public vs internal visibility

## Phase 3: Add documents, policies, and regulations

Goal:

- move from assistance to domain expertise

Changes:

1. Generalize current FAQ document ingestion into generic document ingestion.
2. Add document source types for:
   - insurance policy docs
   - claims docs
   - safety manuals
   - boating regulations
3. Add policy rule extraction and review workflows.

## Phase 4: Platform-wide assistant endpoint

Goal:

- one assistant across boating, harbor, insurance, and safety

Changes:

1. Add a single answer endpoint that composes:
   - user context
   - boat context
   - harbor context
   - knowledge retrieval
2. Reuse this endpoint in:
   - chat
   - dashboard assistant
   - public widget
   - admin support surfaces

## Phase 5: Safety and proactive recommendations

Goal:

- shift from reactive Q&A to proactive guidance

Changes:

1. weather + harbor + boat + policy reasoning
2. risk scoring
3. alerting and recommendation jobs
4. personalized safety reminders

## First Practical PR Sequence

If we were implementing this incrementally in this repo, the safest order would be:

1. Create generic knowledge schema
- migrations for `knowledge_entities`, `knowledge_relationships`, `knowledge_ingestion_runs`

2. Introduce a generic vector store service
- extract Pinecone/OpenAI logic from `CopilotMemoryService`

3. Add harbor and boat entity builders
- jobs that summarize and index `Location` and `Yacht`

4. Add `KnowledgeRetrievalService`
- merge FAQ retrieval with harbor/boat/document retrieval

5. Upgrade `ChatAiContextService`
- replace FAQ-only knowledge payload with generalized knowledge context

6. Expand Knowledge Brain UI/API
- show counts and review states by domain type

7. Add policy/claims ingestion
- document parsing + structured rule review

## Risks And Guardrails

### 1. Hallucinations in insurance and safety

Mitigation:

- require approved sources
- always attach citations
- downgrade confidence when retrieval is weak
- allow forced human handoff

### 2. Unclear source freshness

Mitigation:

- track `indexed_at`, `source_updated_at`, `reviewed_at`
- surface stale knowledge in admin

### 3. Mixing public and internal knowledge

Mitigation:

- keep `visibility` on chunks and entities
- apply role-aware retrieval filters before prompting

### 4. Pinecone becoming a source of truth

Mitigation:

- Pinecone stores retrieval vectors only
- relational DB remains the canonical source

## Recommendation Summary

Your direction is correct, but the best implementation path in this repo is:

1. generalize the current FAQ + Pinecone + Knowledge Brain stack
2. model graph relationships in relational tables first
3. index all approved knowledge into Pinecone with richer metadata
4. use OpenAI only after retrieval, not as the knowledge source itself
5. add high-governance review flows for insurance, claims, and safety

## Suggested Immediate Next Build Step

The best next engineering task is:

**Implement Phase 1: generic knowledge schema + generic vector store service, then rewire FAQ retrieval to use that abstraction.**

That gives NauticSecure a stable base for:

- harbor intelligence
- policy and claims knowledge
- safety recommendations
- a true platform-wide AI assistant
