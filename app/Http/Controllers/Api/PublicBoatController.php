<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;

class PublicBoatController extends Controller
{
    /**
     * GET /api/public/boats/{id}
     * GET /api/public/yachts/{id}
     *
     * Public boat detail endpoint used by the public boat detail page and
     * the widget to resolve location context. Accepts both internal DB id
     * and external yachtshift_id so that URL slugs like /aanbod-boten/2006423/
     * resolve correctly regardless of which ID system was used.
     */
    public function show(string $id): JsonResponse
    {
        if (!ctype_digit($id)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $yacht = Yacht::with(['location', 'images'])
            ->where('id', $id)
            ->orWhere('yachtshift_id', $id)
            ->first();

        if (!$yacht) {
            return response()->json(['error' => 'Not found'], 404);
        }

        // Only expose public-facing fields — never sensitive data.

        return response()->json([
            'id'               => $yacht->id,
            'yachtshift_id'    => $yacht->yachtshift_id,
            'name'             => trim(($yacht->manufacturer ?? '') . ' ' . ($yacht->model ?? $yacht->boat_name ?? '')),
            'title'            => $yacht->boat_name,
            'model'            => $yacht->model,
            'brand'            => $yacht->manufacturer,
            'status'           => $yacht->status,
            'price'            => $yacht->price,
            'build_year'       => $yacht->construction_year ?? $yacht->build_year ?? null,
            'length'           => $yacht->loa ?? $yacht->boat_length ?? null,
            'engine'           => $yacht->engine_type ?? $yacht->engine_manufacturer ?? null,
            'description'      => $yacht->short_description_nl ?? $yacht->short_description_en ?? null,
            'location_id'      => $yacht->location_id,
            'location_name'    => $yacht->location?->name,
            'harbor_name'      => $yacht->location?->name,
            // optimized_url, not the raw url column (a storage-relative
            // path) — same bug already found/fixed for OpenMarine exports:
            // the raw column produces broken relative URLs on the public
            // site instead of the real fully-qualified public URL.
            'images'           => $yacht->images->take(10)->map(fn($img) => [
                'url'  => $img->optimized_url,
                'src'  => $img->optimized_url,
            ])->values()->toArray(),
        ]);
    }
}
