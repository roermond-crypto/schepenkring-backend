<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\FaqKnowledgeDocument;
use App\Models\KnowledgeEntity;
use App\Models\KnowledgeRelationship;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Str;

class KnowledgeGraphService
{
    public function __construct(private KnowledgeVectorStoreService $vectors)
    {
    }

    public function syncFaq(Faq $faq): KnowledgeEntity
    {
        $entity = $this->upsertEntity(
            'faq',
            $faq->getTable(),
            $faq->id,
            [
                'location_id' => $faq->location_id,
                'title' => $faq->question,
                'summary' => Str::limit($faq->answer, 500, ''),
                'language' => $faq->language,
                'status' => $faq->deprecated_at ? 'deprecated' : 'active',
                'metadata' => [
                    'faq_id' => $faq->id,
                    'category' => $faq->category,
                    'department' => $faq->department,
                    'visibility' => $faq->visibility,
                    'brand' => $faq->brand,
                    'model' => $faq->model,
                    'tags' => $faq->tags ?? [],
                    'source_type' => $faq->source_type,
                    'last_indexed_at' => optional($faq->last_indexed_at)->toIso8601String(),
                    'deprecated_at' => optional($faq->deprecated_at)->toIso8601String(),
                    'superseded_by_faq_id' => $faq->superseded_by_faq_id,
                    'trained_by_user_id' => $faq->trained_by_user_id,
                    'helpful' => (int) $faq->helpful,
                    'not_helpful' => (int) $faq->not_helpful,
                ],
            ]
        );

        if ($faq->superseded_by_faq_id) {
            $replacement = Faq::query()->find($faq->superseded_by_faq_id);

            if ($replacement) {
                $replacementEntity = $this->syncFaq($replacement);
                $this->upsertRelationship($entity, $replacementEntity, 'superseded_by', [
                    'source' => 'faq_training',
                ]);
            }
        } else {
            $entity->outgoingRelationships()
                ->where('relationship_type', 'superseded_by')
                ->delete();
        }

        return $entity;
    }

    public function syncLocation(Location $location): KnowledgeEntity
    {
        $entity = $this->upsertEntity(
            'location',
            $location->getTable(),
            $location->id,
            [
                'location_id' => $location->id,
                'title' => $location->name,
                'summary' => Str::limit($this->buildLocationSummary($location), 500, ''),
                'language' => null,
                'status' => strtolower((string) ($location->status ?: 'active')),
                'metadata' => [
                    'location_id' => $location->id,
                    'code' => $location->code,
                    'chat_widget_enabled' => (bool) $location->chat_widget_enabled,
                    'chat_widget_theme' => $location->chat_widget_theme,
                    'chat_widget_welcome_text' => $location->chat_widget_welcome_text,
                ],
            ]
        );

        $this->syncEntityVector($entity, $this->buildLocationKnowledgeText($location), [
            'code' => $location->code,
            'chat_widget_enabled' => (bool) $location->chat_widget_enabled,
            'chat_widget_theme' => $location->chat_widget_theme,
        ]);

        return $entity;
    }

    public function syncYacht(Yacht $yacht): KnowledgeEntity
    {
        $yacht->loadMissing([
            'location:id,name,code,status,chat_widget_enabled,chat_widget_welcome_text,chat_widget_theme',
            'owner:id,client_location_id',
        ]);

        $resolvedLocationId = $this->resolveYachtLocationId($yacht);
        $resolvedLocation = $this->resolveYachtLocation($yacht, $resolvedLocationId);

        $entity = $this->upsertEntity(
            'yacht',
            $yacht->getTable(),
            $yacht->id,
            [
                'location_id' => $resolvedLocationId,
                'title' => $this->yachtTitle($yacht),
                'summary' => Str::limit($this->buildYachtSummary($yacht, $resolvedLocation), 500, ''),
                'language' => null,
                'status' => strtolower((string) ($yacht->status ?: 'draft')),
                'metadata' => [
                    'yacht_id' => $yacht->id,
                    'boat_name' => $yacht->boat_name,
                    'manufacturer' => $yacht->manufacturer,
                    'model' => $yacht->model,
                    'year' => $yacht->year,
                    'price' => $yacht->price,
                    'boat_type' => $yacht->boat_type,
                    'boat_category' => $yacht->boat_category,
                    'location_city' => $yacht->location_city,
                    'location_lat' => $yacht->location_lat,
                    'location_lng' => $yacht->location_lng,
                    'location_id' => $yacht->location_id,
                    'ref_harbor_id' => $yacht->ref_harbor_id,
                    'external_url' => $yacht->external_url,
                    'loa' => $yacht->loa,
                    'beam' => $yacht->beam,
                    'draft' => $yacht->draft,
                    'cabins' => $yacht->cabins,
                    'berths' => $yacht->berths,
                    'fuel' => $yacht->fuel,
                    'engine_manufacturer' => $yacht->engine_manufacturer,
                    'horse_power' => $yacht->horse_power,
                    'resolved_location' => $resolvedLocation ? [
                        'id' => $resolvedLocation->id,
                        'name' => $resolvedLocation->name,
                        'code' => $resolvedLocation->code,
                    ] : null,
                ],
            ]
        );

        if ($resolvedLocation) {
            $locationEntity = $this->syncLocation($resolvedLocation);

            $this->upsertRelationship($entity, $locationEntity, 'located_at', [
                'source' => 'yacht_sync',
            ]);

            $entity->outgoingRelationships()
                ->where('relationship_type', 'located_at')
                ->where('to_entity_id', '!=', $locationEntity->id)
                ->delete();
        } else {
            $entity->outgoingRelationships()
                ->where('relationship_type', 'located_at')
                ->delete();
        }

        $this->syncEntityVector($entity, $this->buildYachtKnowledgeText($yacht, $resolvedLocation), [
            'manufacturer' => $yacht->manufacturer,
            'model' => $yacht->model,
            'year' => $yacht->year,
            'price' => $yacht->price,
            'boat_type' => $yacht->boat_type,
            'boat_category' => $yacht->boat_category,
            'location_city' => $yacht->location_city,
            'ref_harbor_id' => $yacht->ref_harbor_id,
            'resolved_location_id' => $resolvedLocation?->id,
            'resolved_location_code' => $resolvedLocation?->code,
        ]);

        return $entity;
    }

    public function syncDocument(FaqKnowledgeDocument $document): KnowledgeEntity
    {
        return $this->upsertEntity(
            'document',
            $document->getTable(),
            $document->id,
            [
                'location_id' => $document->location_id,
                'title' => $document->file_name,
                'summary' => Str::limit((string) ($document->extracted_text ?? ''), 500, ''),
                'language' => $document->language,
                'status' => match ($document->status) {
                    'failed' => 'failed',
                    'pending_review' => 'active',
                    default => $document->status,
                },
                'metadata' => [
                    'document_id' => $document->id,
                    'file_path' => $document->file_path,
                    'mime_type' => $document->mime_type,
                    'extension' => $document->extension,
                    'source_type' => $document->source_type,
                    'category' => $document->category,
                    'department' => $document->department,
                    'visibility' => $document->visibility,
                    'brand' => $document->brand,
                    'model' => $document->model,
                    'tags' => $document->tags ?? [],
                    'chunk_count' => (int) $document->chunk_count,
                    'generated_qna_count' => (int) $document->generated_qna_count,
                    'processed_at' => optional($document->processed_at)->toIso8601String(),
                    'processing_error' => $document->processing_error,
                ],
            ]
        );
    }

    public function markFaqDeprecated(Faq $faq, ?Faq $replacement = null): ?KnowledgeEntity
    {
        $faq->refresh();
        $entity = $this->syncFaq($faq);

        if ($replacement) {
            $replacementEntity = $this->syncFaq($replacement);
            $this->upsertRelationship($entity, $replacementEntity, 'superseded_by', [
                'source' => 'faq_training',
            ]);
        }

        return $entity;
    }

    public function removeFaq(Faq $faq): void
    {
        $this->removeEntity('faq', $faq->getTable(), $faq->id);
    }

    public function removeLocation(Location $location): void
    {
        $this->removeEntity('location', $location->getTable(), $location->id);
    }

    public function removeYacht(Yacht $yacht): void
    {
        $this->removeEntity('yacht', $yacht->getTable(), $yacht->id);
    }

    private function upsertEntity(string $type, string $sourceTable, int $sourceId, array $attributes): KnowledgeEntity
    {
        $entity = KnowledgeEntity::query()->firstOrNew([
            'type' => $type,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
        ]);

        $entity->fill($attributes);
        $entity->save();

        return $entity;
    }

    private function upsertRelationship(
        KnowledgeEntity $fromEntity,
        KnowledgeEntity $toEntity,
        string $relationshipType,
        array $metadata = [],
        ?float $weight = null
    ): KnowledgeRelationship {
        $relationship = KnowledgeRelationship::query()->firstOrNew([
            'from_entity_id' => $fromEntity->id,
            'to_entity_id' => $toEntity->id,
            'relationship_type' => $relationshipType,
        ]);

        $relationship->weight = $weight;
        $relationship->metadata = $metadata === [] ? null : $metadata;
        $relationship->save();

        return $relationship;
    }

    private function removeEntity(string $type, string $sourceTable, int $sourceId): void
    {
        KnowledgeEntity::query()
            ->where('type', $type)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->delete();

        $this->vectors->delete($this->vectorId($type, $sourceTable, $sourceId));
    }

    private function syncEntityVector(KnowledgeEntity $entity, string $text, array $metadata = []): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $this->vectors->upsertText(
            $this->vectorId($entity->type, (string) $entity->source_table, (int) $entity->source_id),
            $text,
            array_filter(array_merge($metadata, [
                'kind' => 'knowledge_entity',
                'knowledge_entity_id' => $entity->id,
                'knowledge_type' => $entity->type,
                'source_table' => $entity->source_table,
                'source_id' => $entity->source_id,
                'location_id' => $entity->location_id,
                'title' => Str::limit($entity->title, 250, ''),
                'status' => $entity->status,
            ]), static fn ($value) => ! in_array($value, [null, '', []], true))
        );
    }

    private function vectorId(string $type, string $sourceTable, int $sourceId): string
    {
        $source = Str::slug($sourceTable, '-') ?: 'source';

        return sprintf('knowledge-%s-%s-%d', $type, $source, $sourceId);
    }

    private function buildLocationSummary(Location $location): string
    {
        return implode('. ', array_filter([
            $location->name,
            $location->code ? 'Harbor code ' . $location->code : null,
            $location->status ? 'Status ' . strtolower((string) $location->status) : null,
            $location->chat_widget_enabled ? 'Chat widget enabled' : 'Chat widget disabled',
            $location->chat_widget_welcome_text
                ? 'Welcome text: ' . Str::limit((string) $location->chat_widget_welcome_text, 160, '')
                : null,
        ]));
    }

    private function buildLocationKnowledgeText(Location $location): string
    {
        return implode('. ', array_filter([
            'Harbor ' . $location->name,
            $location->code ? 'Location code ' . $location->code : null,
            $location->status ? 'Harbor status ' . strtolower((string) $location->status) : null,
            $location->chat_widget_enabled ? 'Chat assistant is enabled for this harbor' : 'Chat assistant is disabled for this harbor',
            $location->chat_widget_theme ? 'Chat theme ' . $location->chat_widget_theme : null,
            $location->chat_widget_welcome_text
                ? 'Welcome message ' . Str::limit((string) $location->chat_widget_welcome_text, 220, '')
                : null,
        ]));
    }

    private function yachtTitle(Yacht $yacht): string
    {
        return $yacht->boat_name ?: trim(implode(' ', array_filter([$yacht->manufacturer, $yacht->model])));
    }

    private function buildYachtSummary(Yacht $yacht, ?Location $location = null): string
    {
        return implode('. ', array_filter([
            $this->yachtTitle($yacht),
            trim(implode(' ', array_filter([$yacht->manufacturer, $yacht->model]))),
            $yacht->year ? 'Built in ' . $yacht->year : null,
            $yacht->status ? 'Status ' . strtolower((string) $yacht->status) : null,
            $yacht->location_city ? 'Located in ' . $yacht->location_city : null,
            $location?->name ? 'Harbor ' . $location->name : null,
            $yacht->price ? 'Price ' . $yacht->price : null,
            $this->firstAvailableYachtDescription($yacht),
        ]));
    }

    private function buildYachtKnowledgeText(Yacht $yacht, ?Location $location = null): string
    {
        return implode('. ', array_filter([
            'Yacht ' . $this->yachtTitle($yacht),
            $yacht->manufacturer ? 'Manufacturer ' . $yacht->manufacturer : null,
            $yacht->model ? 'Model ' . $yacht->model : null,
            $yacht->year ? 'Year ' . $yacht->year : null,
            $yacht->status ? 'Listing status ' . strtolower((string) $yacht->status) : null,
            $yacht->boat_type ? 'Boat type ' . $yacht->boat_type : null,
            $yacht->boat_category ? 'Boat category ' . $yacht->boat_category : null,
            $yacht->price ? 'Price ' . $yacht->price : null,
            $yacht->location_city ? 'City ' . $yacht->location_city : null,
            $location?->name ? 'Harbor ' . $location->name : null,
            $location?->code ? 'Harbor code ' . $location->code : null,
            $yacht->loa ? 'Length overall ' . $yacht->loa : null,
            $yacht->beam ? 'Beam ' . $yacht->beam : null,
            $yacht->draft ? 'Draft ' . $yacht->draft : null,
            $yacht->cabins ? 'Cabins ' . $yacht->cabins : null,
            $yacht->berths ? 'Berths ' . $yacht->berths : null,
            $yacht->fuel ? 'Fuel ' . $yacht->fuel : null,
            $yacht->engine_manufacturer ? 'Engine manufacturer ' . $yacht->engine_manufacturer : null,
            $yacht->horse_power ? 'Horse power ' . $yacht->horse_power : null,
            $this->firstAvailableYachtDescription($yacht),
            $yacht->owners_comment ? 'Owner comment ' . Str::limit((string) $yacht->owners_comment, 240, '') : null,
            $yacht->known_defects ? 'Known defects ' . Str::limit((string) $yacht->known_defects, 240, '') : null,
            $yacht->external_url ? 'Listing URL ' . $yacht->external_url : null,
        ]));
    }

    private function firstAvailableYachtDescription(Yacht $yacht): ?string
    {
        foreach ([
            $yacht->short_description_en,
            $yacht->short_description_nl,
            $yacht->short_description_de,
            $yacht->short_description_fr,
        ] as $description) {
            $description = trim((string) $description);

            if ($description !== '') {
                return Str::limit($description, 240, '');
            }
        }

        return null;
    }

    private function resolveYachtLocation(Yacht $yacht, ?int $resolvedLocationId = null): ?Location
    {
        if ($yacht->relationLoaded('location') && $yacht->location) {
            return $yacht->location;
        }

        $resolvedLocationId ??= $this->resolveYachtLocationId($yacht);

        if (! $resolvedLocationId) {
            return null;
        }

        return Location::query()->find($resolvedLocationId);
    }

    private function resolveYachtLocationId(Yacht $yacht): ?int
    {
        if ($yacht->location_id) {
            return (int) $yacht->location_id;
        }

        if ($yacht->ref_harbor_id) {
            return (int) $yacht->ref_harbor_id;
        }

        if ($yacht->relationLoaded('owner') && $yacht->owner?->client_location_id) {
            return (int) $yacht->owner->client_location_id;
        }

        if (! $yacht->user_id) {
            return null;
        }

        $ownerLocationId = User::query()
            ->whereKey($yacht->user_id)
            ->value('client_location_id');

        return $ownerLocationId ? (int) $ownerLocationId : null;
    }
}
