<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatField;
use App\Models\BoatFieldPriority;
use App\Services\BoatFieldConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BoatFieldController extends Controller
{
    public function __construct(private readonly BoatFieldConfigService $configService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => 'nullable|string|max:80',
            'block' => 'nullable|string|max:80',
            'search' => 'nullable|string|max:120',
            'active' => 'nullable|boolean',
        ]);

        $query = BoatField::query()
            ->with(['priorities' => fn ($relation) => $relation->orderBy('boat_type_key')])
            ->withCount(['mappings', 'valueObservations'])
            ->withSum('valueObservations as value_observations_total', 'observed_count')
            ->orderBy('step_key')
            ->orderBy('block_key')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (isset($validated['step'])) {
            $query->where('step_key', trim($validated['step']));
        }

        if (isset($validated['block'])) {
            $query->where('block_key', trim($validated['block']));
        }

        if (array_key_exists('active', $validated)) {
            $query->where('is_active', (bool) $validated['active']);
        }

        if (isset($validated['search'])) {
            $search = trim($validated['search']);
            if ($search !== '') {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('internal_key', 'like', '%' . $search . '%')
                        ->orWhere('storage_column', 'like', '%' . $search . '%')
                        ->orWhere('block_key', 'like', '%' . $search . '%')
                        ->orWhere('step_key', 'like', '%' . $search . '%');
                });
            }
        }

        return response()->json([
            'data' => $query->get(),
            'meta' => [
                'storage_targets' => $this->configService->storageTargets(),
            ],
        ]);
    }

    public function show(BoatField $boatField): JsonResponse
    {
        $boatField->load([
            'priorities' => fn ($relation) => $relation->orderBy('boat_type_key'),
            'mappings' => fn ($relation) => $relation->orderBy('source')->orderBy('external_value'),
            'valueObservations' => fn ($relation) => $relation->orderByDesc('observed_count')->orderBy('external_value'),
        ]);

        return response()->json([
            'data' => $boatField,
            'meta' => [
                'storage_targets' => $this->configService->storageTargets(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->persist($request, new BoatField(), 201);
    }

    public function update(Request $request, BoatField $boatField): JsonResponse
    {
        return $this->persist($request, $boatField, 200);
    }

    public function destroy(BoatField $boatField): JsonResponse
    {
        $boatField->delete();

        return response()->json([
            'message' => 'Boat field deleted successfully.',
        ]);
    }

    private function persist(Request $request, BoatField $boatField, int $status): JsonResponse
    {
        $validated = $request->validate([
            'internal_key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('boat_fields', 'internal_key')->ignore($boatField->id),
            ],
            'labels_json' => 'required|array',
            'labels_json.nl' => 'nullable|string|max:255',
            'labels_json.en' => 'nullable|string|max:255',
            'labels_json.de' => 'nullable|string|max:255',
            'labels_json.fr' => 'nullable|string|max:255',
            'options_json' => 'nullable|array',
            'options_json.*.value' => 'required|string|max:120',
            'options_json.*.label' => 'nullable|string|max:255',
            'options_json.*.labels' => 'nullable|array',
            'options_json.*.labels.nl' => 'nullable|string|max:255',
            'options_json.*.labels.en' => 'nullable|string|max:255',
            'options_json.*.labels.de' => 'nullable|string|max:255',
            'options_json.*.labels.fr' => 'nullable|string|max:255',
            'field_type' => 'required|string|max:50',
            'block_key' => 'required|string|max:80',
            'step_key' => 'required|string|max:80',
            'sort_order' => 'nullable|integer|min:0',
            'storage_relation' => 'nullable|string|max:80',
            'storage_column' => 'required|string|max:120',
            'ai_relevance' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'priorities' => 'sometimes|array',
            'priorities.*.boat_type_key' => 'required|string|max:80',
            'priorities.*.priority' => ['required', Rule::in(BoatFieldPriority::PRIORITIES)],
        ]);

        $labels = collect($validated['labels_json'])
            ->map(fn ($value) => is_string($value) ? trim($value) : null)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->all();

        if ($labels === []) {
            return response()->json([
                'message' => 'At least one label translation is required.',
                'errors' => [
                    'labels_json' => ['At least one label translation is required.'],
                ],
            ], 422);
        }

        $this->configService->assertValidStorageTarget(
            $validated['storage_relation'] ?? null,
            $validated['storage_column'],
        );

        $options = collect($validated['options_json'] ?? [])
            ->map(function (array $option) {
                $optionLabels = collect($option['labels'] ?? [])
                    ->map(fn ($value) => is_string($value) ? trim($value) : null)
                    ->filter(fn ($value) => is_string($value) && $value !== '')
                    ->all();

                $fallbackLabel = trim((string) ($option['label'] ?? ''));
                if ($fallbackLabel !== '' && ! array_key_exists('en', $optionLabels)) {
                    $optionLabels['en'] = $fallbackLabel;
                }

                return [
                    'value' => trim((string) ($option['value'] ?? '')),
                    'labels' => $optionLabels,
                ];
            })
            ->filter(fn (array $option) => $option['value'] !== '')
            ->unique('value')
            ->values()
            ->all();

        $field = DB::transaction(function () use ($boatField, $validated, $labels, $options) {
            $boatField->fill([
                'internal_key' => trim($validated['internal_key']),
                'labels_json' => $labels,
                'options_json' => $options !== [] ? $options : null,
                'field_type' => trim($validated['field_type']),
                'block_key' => trim($validated['block_key']),
                'step_key' => trim($validated['step_key']),
                'sort_order' => $validated['sort_order'] ?? 0,
                'storage_relation' => isset($validated['storage_relation']) && trim((string) $validated['storage_relation']) !== ''
                    ? trim((string) $validated['storage_relation'])
                    : null,
                'storage_column' => trim($validated['storage_column']),
                'ai_relevance' => $validated['ai_relevance'] ?? true,
                'is_active' => $validated['is_active'] ?? true,
            ]);
            $boatField->save();

            if (array_key_exists('priorities', $validated)) {
                $this->syncPriorities($boatField, $validated['priorities'] ?? []);
            }

            return $boatField->fresh([
                'priorities' => fn ($relation) => $relation->orderBy('boat_type_key'),
            ]);
        });

        return response()->json([
            'data' => $field,
        ], $status);
    }

    private function syncPriorities(BoatField $boatField, array $priorities): void
    {
        $records = collect($priorities)
            ->map(function (array $priority) {
                return [
                    'boat_type_key' => BoatFieldPriority::normalizeBoatTypeKey($priority['boat_type_key'] ?? null),
                    'priority' => strtolower(trim((string) ($priority['priority'] ?? 'secondary'))),
                ];
            })
            ->unique('boat_type_key')
            ->values();

        $boatField->priorities()->delete();

        if ($records->isNotEmpty()) {
            $boatField->priorities()->createMany($records->all());
        }
    }
}
