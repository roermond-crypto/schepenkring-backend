<?php

namespace App\Http\Controllers;

use App\Models\Harbor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HarborController extends Controller
{
    // ─── PUBLIC ───────────────────────────────────

    /** GET /api/harbors — public list with search/filter + optional distance */
    public function index(Request $request)
    {
        $query = Harbor::published();

        // Distance calculation when user provides their coordinates
        $userLat = $request->input('lat');
        $userLng = $request->input('lng');
        $hasDistance = $userLat && $userLng;

        if ($hasDistance) {
            $userLat = (float) $userLat;
            $userLng = (float) $userLng;

            // Haversine formula in SQL (returns distance in km)
            $query->selectRaw("harbors.*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(lat)) *
                    cos(radians(lng) - radians(?)) +
                    sin(radians(?)) * sin(radians(lat))
                )
            ) AS distance", [$userLat, $userLng, $userLat]);

            // Only include harbors that have coordinates
            $query->whereNotNull('lat')->whereNotNull('lng');
        }

        // Full-text search on name, city, postal_code
        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('city', 'like', "%{$q}%")
                  ->orWhere('postal_code', 'like', "%{$q}%");
            });
        }

        // Filter by city
        if ($city = $request->input('city')) {
            $query->where('city', $city);
        }

        // Filter by facility (JSON contains)
        if ($facility = $request->input('facility')) {
            $query->whereJsonContains('facilities', $facility);
        }

        // Sort options (supports 'distance' when coordinates provided)
        $sort = $request->input('sort', $hasDistance ? 'distance' : 'name');
        $dir  = $request->input('dir', 'asc');

        if ($sort === 'distance' && $hasDistance) {
            $query->orderBy('distance', $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $harbors = $query->paginate($request->input('per_page', 20));

        return response()->json($harbors);
    }

    /** GET /api/harbors/{id} — single harbor */
    public function show($id)
    {
        $harbor = Harbor::with('pages')->findOrFail($id);
        return response()->json($harbor);
    }

    /** GET /api/harbors/slug/{slug} — harbor by slug */
    public function showBySlug($slug)
    {
        $query = Harbor::with('pages')->where('is_published', true);
        $harbor = (clone $query)->where('slug', $slug)->first();
        if (!$harbor) {
            $harbor = (clone $query)
                ->whereNotNull('hiswa_company_id')
                ->whereRaw("CONCAT(slug, '-', hiswa_company_id) = ?", [$slug])
                ->first();
        }

        abort_if(!$harbor, 404);

        return response()->json($harbor);
    }

    // ─── ADMIN ────────────────────────────────────

    /** GET /api/admin/harbors — admin list (all harbors, any status) */
    public function adminIndex(Request $request)
    {
        $query = Harbor::query();

        if ($q = $request->input('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('city', 'like', "%{$q}%");
            });
        }

        if ($request->boolean('needs_review')) {
            $query->needsReview();
        }

        if ($request->boolean('needs_geocode')) {
            $query->needsGeocode();
        }

        if ($request->boolean('unpublished')) {
            $query->where('is_published', false);
        }

        if ($request->boolean('missing_contacts')) {
            $query->missingContacts();
        }

        $query->orderBy('updated_at', 'desc');

        return response()->json($query->paginate(25));
    }

    /** POST /api/admin/harbors — create */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'street_address' => 'nullable|string|max:255',
            'postal_code'    => 'nullable|string|max:10',
            'city'           => 'nullable|string|max:100',
            'province'       => 'nullable|string|max:100',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:30',
            'website'        => 'nullable|url|max:255',
            'facilities'     => 'nullable|array',
            'tags'           => 'nullable|array',
            'is_published'   => 'nullable|boolean',
        ]);

        $validated['slug'] = Harbor::generateSlug(
            $validated['name'],
            $validated['city'] ?? null
        );

        $harbor = Harbor::create($validated);

        return response()->json($harbor, 201);
    }

    /** PUT /api/admin/harbors/{id} — update */
    public function update(Request $request, $id)
    {
        $harbor = Harbor::findOrFail($id);

        $validated = $request->validate([
            'name'           => 'sometimes|required|string|max:255',
            'description'    => 'nullable|string',
            'street_address' => 'nullable|string|max:255',
            'postal_code'    => 'nullable|string|max:10',
            'city'           => 'nullable|string|max:100',
            'province'       => 'nullable|string|max:100',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:30',
            'website'        => 'nullable|url|max:255',
            'facilities'     => 'nullable|array',
            'tags'           => 'nullable|array',
            'is_published'   => 'nullable|boolean',
            'needs_review'   => 'nullable|boolean',
        ]);

        $harbor->update($validated);

        if ($this->addressWasUpdated($harbor, $validated)) {
            $this->resetGeocodeState($harbor);
        }

        return response()->json($harbor->fresh());
    }

    /** DELETE /api/admin/harbors/{id} */
    public function destroy($id)
    {
        $harbor = Harbor::findOrFail($id);
        $harbor->delete();

        return response()->json(['message' => 'Harbor deleted']);
    }

    /** GET /api/admin/harbors/needs-review */
    public function needsReview()
    {
        $harbors = Harbor::needsReview()
            ->orderBy('updated_at', 'desc')
            ->paginate(25);

        return response()->json($harbors);
    }

    /** POST /api/admin/harbors/{id}/enrich — trigger Google enrichment */
    public function enrich($id)
    {
        $harbor = Harbor::findOrFail($id);

        // Dispatch enrichment job
        \App\Jobs\EnrichHarborJob::dispatch($harbor);

        return response()->json(['message' => 'Enrichment queued', 'harbor_id' => $harbor->id]);
    }

    /** POST /api/admin/harbors/{id}/geocode — re-run geocode only */
    public function geocode($id)
    {
        $harbor = Harbor::findOrFail($id);
        \App\Jobs\GeocodeHarborJob::dispatch($harbor);

        return response()->json(['message' => 'Geocode queued', 'harbor_id' => $harbor->id]);
    }

    /** POST /api/admin/harbors/{id}/place-details — re-run place details */
    public function placeDetails($id)
    {
        $harbor = Harbor::findOrFail($id);
        \App\Jobs\RefreshHarborPlaceDetailsJob::dispatch($harbor, true);

        return response()->json(['message' => 'Place details queued', 'harbor_id' => $harbor->id]);
    }

    /** POST /api/admin/harbors/{id}/third-party-enrich — optional enrichment for missing contacts */
    public function thirdPartyEnrich($id)
    {
        $harbor = Harbor::findOrFail($id);
        \App\Jobs\EnrichHarborThirdPartyJob::dispatch($harbor);

        return response()->json(['message' => 'Third-party enrichment queued', 'harbor_id' => $harbor->id]);
    }

    /** POST /api/admin/harbors/{id}/generate-page — trigger AI page generation */
    public function generatePage($id)
    {
        $harbor = Harbor::findOrFail($id);

        // Dispatch page generation job
        \App\Jobs\GenerateHarborPageJob::dispatch($harbor);

        return response()->json(['message' => 'Page generation queued', 'harbor_id' => $harbor->id]);
    }

    /** POST /api/admin/harbors/{id}/publish — toggle publish */
    public function togglePublish($id)
    {
        $harbor = Harbor::findOrFail($id);
        $harbor->update(['is_published' => !$harbor->is_published]);

        return response()->json([
            'message' => $harbor->is_published ? 'Published' : 'Unpublished',
            'is_published' => $harbor->is_published,
        ]);
    }

    /** GET /api/admin/harbors/stats — dashboard stats */
    public function stats()
    {
        return response()->json([
            'total'          => Harbor::count(),
            'published'      => Harbor::published()->count(),
            'needs_review'   => Harbor::needsReview()->count(),
            'needs_geocode'  => Harbor::needsGeocode()->count(),
            'needs_details'  => Harbor::needsPlaceDetails()->count(),
            'missing_contacts' => Harbor::missingContacts()->count(),
            'with_pages'     => Harbor::whereHas('pages')->count(),
        ]);
    }

    /** GET /api/admin/harbors/export-magazine — generate and download zip */
    public function exportMagazine(Request $request)
    {
        \Illuminate\Support\Facades\Artisan::call('harbors:export-magazine', [
            '--published-only' => true
        ]);

        $zipFile = storage_path('app/magazine_export_latest.zip');
        
        if (file_exists($zipFile)) {
            return response()->download($zipFile, 'jachthavens_magazine_export_' . now()->format('Y_m_d') . '.zip');
        }

        return response()->json(['message' => 'Export failed to generate'], 500);
    }

    private function addressWasUpdated(Harbor $harbor, array $validated): bool
    {
        foreach (['street_address', 'postal_code', 'city', 'province', 'country'] as $field) {
            if (array_key_exists($field, $validated) && $harbor->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }

    private function resetGeocodeState(Harbor $harbor): void
    {
        $harbor->update([
            'gmaps_place_id' => null,
            'gmaps_formatted_address' => null,
            'lat' => null,
            'lng' => null,
            'address_components' => null,
            'geocode_confidence' => null,
            'maps_url' => null,
            'geocode_query_hash' => null,
            'last_geocode_at' => null,
            'last_place_details_fetch_at' => null,
            'last_place_photos_fetch_at' => null,
        ]);
    }
}
