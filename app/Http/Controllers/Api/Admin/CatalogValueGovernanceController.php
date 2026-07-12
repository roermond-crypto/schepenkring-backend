<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogValue;
use App\Services\CatalogValueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin/employee-only mutations on catalog values — archive and merge.
 * Deliberately not a standalone CRUD page: these actions are surfaced
 * inline inside the autocomplete dropdown (BoatFieldSettingsPage's field
 * config drives which fields allow them via allow_inline_archive).
 */
class CatalogValueGovernanceController extends Controller
{
    public function __construct(private readonly CatalogValueService $catalogValues)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_key' => 'required|string|max:120',
            'status' => 'nullable|string|in:active,archived',
            'q' => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CatalogValue::query()
            ->forField($validated['field_key'])
            ->when(
                isset($validated['status']),
                fn ($q) => $q->where('status', $validated['status']),
            )
            ->when(
                isset($validated['q']) && trim($validated['q']) !== '',
                fn ($q) => $q->where('value', 'like', '%' . trim($validated['q']) . '%'),
            )
            ->orderByDesc('usage_count')
            ->orderBy('value');

        return response()->json(
            $query->paginate($validated['per_page'] ?? 50),
        );
    }

    public function archive(Request $request, CatalogValue $catalogValue): JsonResponse
    {
        $validated = $request->validate([
            'merge_into_id' => 'nullable|integer|exists:catalog_values,id',
        ]);

        try {
            $result = $this->catalogValues->archive(
                $catalogValue,
                $request->user(),
                $validated['merge_into_id'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Unable to archive this value.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function merge(Request $request, CatalogValue $catalogValue): JsonResponse
    {
        $validated = $request->validate([
            'target_id' => 'required|integer|exists:catalog_values,id',
        ]);

        try {
            $target = CatalogValue::findOrFail($validated['target_id']);
            $result = $this->catalogValues->merge($catalogValue, $target, $request->user());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Unable to merge these values.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json(['data' => $result]);
    }
}
