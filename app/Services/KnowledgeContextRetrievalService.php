<?php

namespace App\Services;

use App\Models\KnowledgeEntity;
use App\Models\Location;
use App\Models\Yacht;

class KnowledgeContextRetrievalService
{
    public function __construct(private KnowledgeVectorStoreService $vectors)
    {
    }

    /**
     * @return array{
     *     entities: array<int, array<string, mixed>>,
     *     matches: array<int, array<string, mixed>>,
     *     trace: array<string, mixed>
     * }
     */
    public function retrieve(
        string $question,
        ?Location $location = null,
        ?Yacht $yacht = null,
        int $limit = 4
    ): array {
        $entities = [];

        $seedLocation = $location
            ? $this->findEntityForModel('location', $location->getTable(), $location->id)
            : null;

        $seedYacht = $yacht
            ? $this->findEntityForModel('yacht', $yacht->getTable(), $yacht->id)
            : null;

        foreach ([$seedLocation, $seedYacht] as $seed) {
            if ($seed) {
                $entities[$seed->id] = [
                    'entity' => $seed,
                    'source' => $seed->type === 'location' ? 'conversation_location' : 'conversation_yacht',
                    'match_score' => null,
                ];
            }
        }

        $rawMatches = $this->searchRelatedEntities($question, $location?->id, $limit);
        $matchedEntityIds = array_values(array_unique(array_filter(array_map(
            fn (array $match) => isset($match['metadata']['knowledge_entity_id']) ? (int) $match['metadata']['knowledge_entity_id'] : null,
            $rawMatches
        ))));

        $matchedEntities = $matchedEntityIds === []
            ? collect()
            : KnowledgeEntity::query()
                ->with([
                    'location:id,name,code',
                    'outgoingRelationships.toEntity.location:id,name,code',
                ])
                ->whereIn('id', $matchedEntityIds)
                ->get()
                ->keyBy('id');

        foreach ($rawMatches as $match) {
            $entityId = isset($match['metadata']['knowledge_entity_id'])
                ? (int) $match['metadata']['knowledge_entity_id']
                : null;

            $entity = $entityId ? $matchedEntities->get($entityId) : null;

            if (! $entity) {
                continue;
            }

            $existing = $entities[$entity->id] ?? null;
            $matchScore = (float) ($match['score'] ?? 0.0);

            $entities[$entity->id] = [
                'entity' => $entity,
                'source' => $existing['source'] ?? 'vector_match',
                'match_score' => max((float) ($existing['match_score'] ?? 0.0), $matchScore),
            ];
        }

        uasort($entities, function (array $left, array $right) {
            $priority = [
                'conversation_yacht' => 0,
                'conversation_location' => 1,
                'vector_match' => 2,
            ];

            $leftPriority = $priority[$left['source']] ?? 99;
            $rightPriority = $priority[$right['source']] ?? 99;

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return (float) ($right['match_score'] ?? 0.0) <=> (float) ($left['match_score'] ?? 0.0);
        });

        return [
            'entities' => array_values(array_map(
                fn (array $entry) => $this->serializeEntity(
                    $entry['entity'],
                    $entry['source'],
                    $entry['match_score']
                ),
                $entities
            )),
            'matches' => array_values(array_map(
                fn (array $match) => $this->serializeMatch($match, $matchedEntities),
                $rawMatches
            )),
            'trace' => [
                'strategy' => $rawMatches === [] ? 'graph_seed_only' : 'graph_seed_plus_vector_search',
                'vector_enabled' => $this->vectors->isEnabled(),
                'seed_entity_ids' => array_values(array_map(
                    fn (KnowledgeEntity $entity) => $entity->id,
                    array_values(array_filter([$seedLocation, $seedYacht]))
                )),
                'matched_entity_ids' => $matchedEntityIds,
                'location_scope' => $location?->id,
            ],
        ];
    }

    private function findEntityForModel(string $type, string $sourceTable, int $sourceId): ?KnowledgeEntity
    {
        return KnowledgeEntity::query()
            ->with([
                'location:id,name,code',
                'outgoingRelationships.toEntity.location:id,name,code',
            ])
            ->where('type', $type)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchRelatedEntities(string $question, ?int $locationId, int $limit): array
    {
        $filter = [
            'kind' => ['$eq' => 'knowledge_entity'],
        ];

        if ($locationId) {
            $filter['location_id'] = ['$eq' => $locationId];
        }

        return $this->vectors->search($question, $limit, $filter);
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  \Illuminate\Support\Collection<int, KnowledgeEntity>  $matchedEntities
     * @return array<string, mixed>
     */
    private function serializeMatch(array $match, $matchedEntities): array
    {
        $metadata = $match['metadata'] ?? [];
        $entityId = isset($metadata['knowledge_entity_id']) ? (int) $metadata['knowledge_entity_id'] : null;
        $entity = $entityId ? $matchedEntities->get($entityId) : null;

        return $this->compactArray([
            'knowledge_entity_id' => $entityId,
            'type' => $entity?->type ?? ($metadata['knowledge_type'] ?? null),
            'title' => $entity?->title ?? ($metadata['title'] ?? null),
            'source_ref' => $entity ? $this->sourceReference($entity) : null,
            'score' => round((float) ($match['score'] ?? 0.0), 3),
            'excerpt' => $metadata['text'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntity(KnowledgeEntity $entity, string $source, ?float $matchScore = null): array
    {
        return $this->compactArray([
            'knowledge_entity_id' => $entity->id,
            'type' => $entity->type,
            'source_ref' => $this->sourceReference($entity),
            'source' => $source,
            'title' => $entity->title,
            'summary' => $entity->summary,
            'status' => $entity->status,
            'location_id' => $entity->location_id,
            'match_score' => $matchScore ? round($matchScore, 3) : null,
            'location' => $entity->location ? $this->compactArray([
                'id' => $entity->location->id,
                'name' => $entity->location->name,
                'code' => $entity->location->code,
            ]) : null,
            'metadata' => $entity->metadata,
            'relationships' => $entity->outgoingRelationships
                ->map(fn ($relationship) => $this->compactArray([
                    'type' => $relationship->relationship_type,
                    'to_entity_id' => $relationship->toEntity?->id,
                    'to_type' => $relationship->toEntity?->type,
                    'to_title' => $relationship->toEntity?->title,
                    'to_source_ref' => $relationship->toEntity ? $this->sourceReference($relationship->toEntity) : null,
                ]))
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    private function sourceReference(KnowledgeEntity $entity): string
    {
        return match ($entity->type) {
            'faq' => 'faq:' . $entity->source_id,
            'location' => 'location:' . $entity->source_id,
            'yacht' => 'yacht:' . $entity->source_id,
            default => 'knowledge_entity:' . $entity->id,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function compactArray(array $data): array
    {
        return array_filter($data, static fn ($value) => ! in_array($value, [null, '', []], true));
    }
}
