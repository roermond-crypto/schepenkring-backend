<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SellerDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerDashboardController extends Controller
{
    public function __construct(private SellerDashboardService $dashboard)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->dashboard->summary($request->user());

        return response()->json($summary);
    }
}
