<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiQualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiQualityController extends Controller
{
    public function __construct(
        private readonly AiQualityService $service,
    ) {
    }

    private function platformIdFrom(Request $request): ?int
    {
        return $request->filled('platform_id') ? (int) $request->input('platform_id') : null;
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->service->summary($this->platformIdFrom($request)));
    }

    public function feedback(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 50);

        return response()->json($this->service->feedback($this->platformIdFrom($request), $perPage));
    }
}
