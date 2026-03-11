<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogAutocompleteController extends Controller
{
    public function brands(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $brands = Yacht::query()
            ->selectRaw('MIN(id) as id, manufacturer as name')
            ->whereNotNull('manufacturer')
            ->whereRaw("TRIM(manufacturer) <> ''")
            ->when($query !== '', function ($q) use ($query) {
                $q->where('manufacturer', 'like', '%' . $query . '%');
            })
            ->groupBy('manufacturer')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                ];
            })
            ->values();

        return response()->json($brands);
    }

    public function models(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $brandId = $request->query('brand_id');
        $manufacturer = null;

        if ($brandId !== null && $brandId !== '') {
            $manufacturer = Yacht::query()
                ->whereKey((int) $brandId)
                ->value('manufacturer');

            if (!$manufacturer && is_string($brandId)) {
                $manufacturer = trim($brandId);
            }
        }

        $models = Yacht::query()
            ->selectRaw('MIN(id) as id, model as name')
            ->whereNotNull('model')
            ->whereRaw("TRIM(model) <> ''")
            ->when($manufacturer, function ($q) use ($manufacturer) {
                $q->where('manufacturer', $manufacturer);
            })
            ->when($query !== '', function ($q) use ($query) {
                $q->where('model', 'like', '%' . $query . '%');
            })
            ->groupBy('model')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                ];
            })
            ->values();

        return response()->json($models);
    }

    public function types(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $types = Yacht::query()
            ->selectRaw('MIN(id) as id, boat_type as name')
            ->whereNotNull('boat_type')
            ->whereRaw("TRIM(boat_type) <> ''")
            ->when($query !== '', function ($q) use ($query) {
                $q->where('boat_type', 'like', '%' . $query . '%');
            })
            ->groupBy('boat_type')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                ];
            })
            ->values();

        return response()->json($types);
    }
}
