<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BoatFieldConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoatFormConfigController extends Controller
{
    public function __construct(private readonly BoatFieldConfigService $configService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'boat_type' => 'nullable|string|max:80',
            'step' => 'nullable|string|max:80',
            'locale' => 'nullable|string|max:10',
        ]);

        $config = $this->configService->build(
            $validated['boat_type'] ?? null,
            $validated['step'] ?? null,
            $validated['locale'] ?? null,
        );

        return response()->json([
            'data' => $config,
        ]);
    }
}
