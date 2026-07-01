<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContractTypeController extends Controller
{
    // GET /api/admin/contract-types
    public function index(): JsonResponse
    {
        $types = ContractType::orderBy('sort_order')->orderBy('name')->get();
        return response()->json(['data' => $types]);
    }

    // POST /api/admin/contract-types
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'slug'        => 'nullable|string|max:100|unique:contract_types,slug',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name'], '_');

        // Ensure slug uniqueness by appending a counter if needed
        $baseSlug = $slug;
        $counter  = 1;
        while (ContractType::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '_' . $counter++;
        }

        $type = ContractType::create([
            'name'        => $data['name'],
            'slug'        => $slug,
            'description' => $data['description'] ?? null,
            'is_active'   => true,
            'sort_order'  => $data['sort_order'] ?? (ContractType::max('sort_order') + 1),
        ]);

        return response()->json(['data' => $type], 201);
    }

    // PATCH /api/admin/contract-types/{contractType}
    public function update(Request $request, ContractType $contractType): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $contractType->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['data' => $contractType->fresh()]);
    }

    // DELETE /api/admin/contract-types/{contractType}
    public function destroy(ContractType $contractType): JsonResponse
    {
        // Soft-deactivate instead of hard-delete to preserve template links
        $contractType->update(['is_active' => false]);
        return response()->json(['message' => 'Type gedeactiveerd.']);
    }
}
