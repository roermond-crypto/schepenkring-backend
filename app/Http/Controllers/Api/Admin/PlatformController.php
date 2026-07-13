<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePlatformRequest;
use App\Http\Requests\Api\UpdatePlatformRequest;
use App\Models\AuditLog;
use App\Models\Platform;
use App\Services\PlatformHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlatformController extends Controller
{
    private const MASKED_CREDENTIAL_KEYS = ['api_secret', 'webhook_secret'];

    public function __construct(
        private readonly PlatformHealthService $health,
    ) {
    }

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

    public function store(StorePlatformRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $platform = Platform::create($data);

        AuditLog::create([
            'action'      => 'platform.created',
            'risk_level'  => 'medium',
            'result'      => 'success',
            'actor_id'    => $request->user()?->id,
            'entity_type' => 'platform',
            'entity_id'   => $platform->id,
            'meta'        => ['name' => $platform->name, 'category' => $platform->category],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json($platform, 201);
    }

    public function show(Platform $platform): JsonResponse
    {
        return response()->json($platform);
    }

    public function update(UpdatePlatformRequest $request, Platform $platform): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('credentials', $data)) {
            $existing = $platform->getRawOriginal('credentials');
            $existing = is_string($existing) ? (json_decode($existing, true) ?? []) : ($existing ?? []);

            foreach (self::MASKED_CREDENTIAL_KEYS as $key) {
                if (($data['credentials'][$key] ?? null) === Platform::MASK_PLACEHOLDER) {
                    unset($data['credentials'][$key]);
                }
            }

            $data['credentials'] = array_merge($existing, $data['credentials']);
        }

        $platform->update($data);

        AuditLog::create([
            'action'      => 'platform.updated',
            'risk_level'  => 'low',
            'result'      => 'success',
            'actor_id'    => $request->user()?->id,
            'entity_type' => 'platform',
            'entity_id'   => $platform->id,
            'meta'        => ['fields' => array_keys($data)],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json($platform->fresh());
    }

    public function destroy(Request $request, Platform $platform): JsonResponse
    {
        $name = $platform->name;
        $platformId = $platform->id;

        $platform->delete();

        AuditLog::create([
            'action'      => 'platform.deleted',
            'risk_level'  => 'high',
            'result'      => 'success',
            'actor_id'    => $request->user()?->id,
            'entity_type' => 'platform',
            'entity_id'   => $platformId,
            'meta'        => ['name' => $name],
            'ip_address'  => $request->ip(),
        ]);

        return response()->json(['message' => 'Platform deleted']);
    }

    public function health(Platform $platform): JsonResponse
    {
        return response()->json($this->health->forPlatform($platform));
    }
}
