<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoatMatchController extends Controller
{
    // POST /boats/match
    public function match(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year'  => 'nullable|integer|min:1900|max:2100',
        ]);

        $brand = trim($validated['brand'] ?? '');
        $model = trim($validated['model'] ?? '');
        $year  = isset($validated['year']) ? (int) $validated['year'] : null;

        if (!$brand && !$model) {
            return response()->json([
                'matched'             => false,
                'match_type'          => 'none',
                'message'             => 'No brand or model provided.',
                'boat'                => null,
                'similar_boats_count' => 0,
                'template'            => null,
            ]);
        }

        // Try exact match first (brand + model + year)
        $query = Yacht::query()->whereNotNull('manufacturer');

        if ($brand) {
            $query->where(fn ($q) => $q
                ->whereRaw('LOWER(manufacturer) = ?', [strtolower($brand)])
                ->orWhereRaw('LOWER(manufacturer) LIKE ?', ['%' . strtolower($brand) . '%'])
            );
        }

        if ($model) {
            $query->where(fn ($q) => $q
                ->whereRaw('LOWER(model) = ?', [strtolower($model)])
                ->orWhereRaw('LOWER(model) LIKE ?', ['%' . strtolower($model) . '%'])
            );
        }

        $similarCount = $query->count();

        // Apply year filter for the "best match" candidate
        $bestQuery = clone $query;
        if ($year) {
            $bestQuery->where('year', $year);
        }

        $best = $bestQuery->latest()->first() ?? $query->latest()->first();

        if (!$best) {
            return response()->json([
                'matched'             => false,
                'match_type'          => 'none',
                'message'             => 'No matching boat found.',
                'boat'                => null,
                'similar_boats_count' => 0,
                'template'            => null,
            ]);
        }

        $matchType = 'partial';
        if ($brand && $model && $year && $best->year == $year) {
            $matchType = 'exact';
        } elseif ($brand && $model) {
            $matchType = 'fuzzy';
        }

        // Derive year range from similar boats
        $yearRange = Yacht::query()
            ->whereRaw('LOWER(manufacturer) LIKE ?', ['%' . strtolower($brand ?: 'x') . '%'])
            ->selectRaw('MIN(year) as min_year, MAX(year) as max_year')
            ->first();

        return response()->json([
            'matched'    => true,
            'match_type' => $matchType,
            'message'    => "Found {$similarCount} similar boat(s).",
            'boat'       => [
                'id'                 => $best->id,
                'brand'              => $best->manufacturer,
                'model'              => $best->model,
                'year'               => $best->year,
                'boat_name'          => $best->boat_name,
                'boat_type'          => $best->boat_type,
                'boat_category'      => $best->boat_category,
                'engine_type'        => $best->engine_type,
                'engine_manufacturer' => $best->engine_manufacturer,
                'fuel'               => $best->fuel,
                'hull_type'          => $best->hull_type,
                'hull_construction'  => $best->hull_construction,
                'common_specs'       => [],
            ],
            'similar_boats_count' => $similarCount,
            'template'            => null,
            'year_range'          => [
                'min' => $yearRange?->min_year,
                'max' => $yearRange?->max_year,
            ],
        ]);
    }
}
