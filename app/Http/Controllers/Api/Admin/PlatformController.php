<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlatformController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Platform::orderBy('priority')->orderBy('name');

        if ($request->boolean('is_active', false) && $request->has('is_active')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'slug'                    => 'nullable|string|max:100|unique:platforms,slug',
            'logo_url'                => 'nullable|url',
            'website_url'             => 'nullable|url',
            'type'                    => 'nullable|string|in:openmarine,xml,api,manual',
            'export_method'           => 'nullable|string|in:openmarine,xml,api,manual',
            'feed_url'                => 'nullable|url',
            'api_url'                 => 'nullable|url',
            'credentials'             => 'nullable|array',
            'openmarine_enabled'      => 'boolean',
            'openmarine_dealer_id'    => 'nullable|string|max:100',
            'openmarine_version'      => 'nullable|string|max:10',
            'openmarine_category_map' => 'nullable|array',
            'supported_countries'     => 'nullable|array',
            'supported_languages'     => 'nullable|array',
            'contact_name'            => 'nullable|string|max:255',
            'contact_email'           => 'nullable|email',
            'notes'                   => 'nullable|string',
            'priority'                => 'nullable|integer',
            'is_active'               => 'boolean',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $platform = Platform::create($data);

        return response()->json($platform, 201);
    }

    public function show(Platform $platform): JsonResponse
    {
        return response()->json($platform);
    }

    public function update(Request $request, Platform $platform): JsonResponse
    {
        $data = $request->validate([
            'name'                    => 'sometimes|string|max:255',
            'slug'                    => 'sometimes|string|max:100|unique:platforms,slug,' . $platform->id,
            'logo_url'                => 'nullable|url',
            'website_url'             => 'nullable|url',
            'type'                    => 'nullable|string|in:openmarine,xml,api,manual',
            'export_method'           => 'nullable|string|in:openmarine,xml,api,manual',
            'feed_url'                => 'nullable|url',
            'api_url'                 => 'nullable|url',
            'credentials'             => 'nullable|array',
            'openmarine_enabled'      => 'boolean',
            'openmarine_dealer_id'    => 'nullable|string|max:100',
            'openmarine_version'      => 'nullable|string|max:10',
            'openmarine_category_map' => 'nullable|array',
            'supported_countries'     => 'nullable|array',
            'supported_languages'     => 'nullable|array',
            'contact_name'            => 'nullable|string|max:255',
            'contact_email'           => 'nullable|email',
            'notes'                   => 'nullable|string',
            'priority'                => 'nullable|integer',
            'is_active'               => 'boolean',
        ]);

        $platform->update($data);

        return response()->json($platform);
    }

    public function destroy(Platform $platform): JsonResponse
    {
        $platform->delete();

        return response()->json(['message' => 'Platform deleted']);
    }
}
