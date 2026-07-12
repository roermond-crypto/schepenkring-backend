<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoatField;
use App\Models\CatalogValue;
use App\Services\CatalogValueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Search + inline create for governed catalog fields (Brand, Model, Boat
 * Type, etc.). Reachable by any authenticated actor editing a yacht (seller,
 * broker, admin) — the same wizard is shared across all of them, so
 * create-new-value must be too. Archive/merge (CatalogValueGovernanceController)
 * are admin/employee-only.
 */
class CatalogValueController extends Controller
{
    public function __construct(private readonly CatalogValueService $catalogValues)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_key' => 'required|string|max:120',
            'q' => 'nullable|string|max:200',
            'parent_value_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $field = BoatField::query()->where('internal_key', $validated['field_key'])->first();

        $results = $this->catalogValues->search(
            $validated['field_key'],
            trim($validated['q'] ?? ''),
            $validated['parent_value_id'] ?? null,
            $validated['limit'] ?? 20,
        );

        return response()->json([
            'data' => $results->map(fn (CatalogValue $value) => $this->present($value)),
            'meta' => [
                'field' => $field ? [
                    'internal_key' => $field->internal_key,
                    'allow_new_values' => $field->allow_new_values,
                    'allow_inline_archive' => $field->allow_inline_archive,
                    'fuzzy_matching' => $field->fuzzy_matching,
                ] : null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_key' => 'required|string|max:120',
            'value' => 'required|string|max:255',
            'parent_value_id' => 'nullable|integer|exists:catalog_values,id',
            'force' => 'nullable|boolean',
        ]);

        try {
            $value = $this->catalogValues->findOrCreate(
                $validated['field_key'],
                $validated['value'],
                $request->user(),
                'manual',
                $validated['parent_value_id'] ?? null,
                (bool) ($validated['force'] ?? false),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'suggestions' => collect($e->errors()['_suggestions'] ?? [])
                    ->map(fn ($v) => $this->present($v))
                    ->all(),
            ], 422);
        }

        return response()->json(['data' => $this->present($value)], 201);
    }

    private function present(CatalogValue $value): array
    {
        return [
            'id' => $value->id,
            'value' => $value->value,
            'field_key' => $value->field_key,
            'usage_count' => $value->usage_count,
            'status' => $value->status,
            'parent_value_id' => $value->parent_value_id,
        ];
    }
}
