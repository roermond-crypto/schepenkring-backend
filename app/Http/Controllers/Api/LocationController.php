<?php

namespace App\Http\Controllers\Api;

use App\Enums\LocationRole;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $query = Location::query();

        $includeInactive = filter_var($request->query('include_inactive'), FILTER_VALIDATE_BOOL);
        if (! $includeInactive) {
            $query->where('status', 'ACTIVE');
        }

        $query->where('code', '!=', 'HQ');

        return response()->json($query->orderBy('name')->get());
    }

    /**
     * GET /api/public/locations/{slug}
     *
     * Public location page data — used by the /vestigingen/{slug} page.
     * Only exposes locations that are active and explicitly marked public.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $location = Location::query()
            ->where('slug', $slug)
            ->where('status', 'ACTIVE')
            ->where('public_visible', true)
            ->first();

        if (! $location) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $locale = in_array($request->query('locale'), ['nl', 'en', 'de'], true)
            ? $request->query('locale')
            : 'nl';
        $description = $location->{"description_{$locale}"}
            ?? $location->description_nl
            ?? $location->description_en
            ?? null;

        $team = $location->users()
            ->whereIn('users.type', [UserType::EMPLOYEE->value, UserType::PARTNER->value])
            ->get(['users.id', 'users.name', 'users.avatar', 'users.type'])
            ->filter(fn ($user) => ! array_key_exists('active', $user->pivot->getAttributes()) || $user->pivot->active)
            ->map(fn ($user) => [
                'id'     => $user->id,
                'name'   => $user->name,
                'avatar' => $user->avatar,
                'role'   => LocationRole::tryFrom((string) $user->pivot->role)?->label(),
            ])
            ->values();

        $boats = $location->yachts()
            ->where('status', 'ACTIVE')
            ->orderByDesc('created_at')
            ->limit(24)
            ->get()
            ->map(fn ($yacht) => [
                'id'             => $yacht->id,
                'name'           => trim(($yacht->manufacturer ?? '').' '.($yacht->model ?? $yacht->boat_name ?? '')) ?: $yacht->boat_name,
                'price'          => $yacht->price,
                'year'           => $yacht->construction_year ?? $yacht->build_year ?? null,
                'main_image_url' => $yacht->main_image_url,
            ])
            ->values();

        return response()->json([
            'id'              => $location->id,
            'name'            => $location->name,
            'slug'            => $location->slug,
            'code'            => $location->code,
            'address_line1'   => $location->address_line1,
            'street_number'   => $location->street_number,
            'postal_code'     => $location->postal_code,
            'city'            => $location->city,
            'country'         => $location->country,
            'phone'           => $location->phone,
            'email'           => $location->email,
            'website'         => $location->website,
            'latitude'        => $location->latitude,
            'longitude'       => $location->longitude,
            'opening_hours'   => $location->opening_hours,
            'hero_image'      => $location->hero_image,
            'location_color'  => $location->location_color,
            'description'     => $description,
            'seo_title'       => $location->seo_title,
            'seo_description' => $location->seo_description,
            'seo_keywords'    => $location->seo_keywords,
            'team'            => $team,
            'boats'           => $boats,
        ]);
    }
}
