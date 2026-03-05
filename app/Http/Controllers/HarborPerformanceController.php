<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HarborPerformanceService;
use Illuminate\Http\Request;

class HarborPerformanceController extends Controller
{
    public function __construct(private HarborPerformanceService $service)
    {
    }

    public function index(Request $request)
    {
        $this->validateRequest($request);
        $range = $this->resolveDateRange($request);
        $filters = $this->extractFilters($request);

        $report = $this->service->buildReport($range['start'], $range['end'], $filters);

        return response()->json($report);
    }

    public function show(User $harbor, Request $request)
    {
        $this->ensurePartner($harbor);
        $this->validateRequest($request);
        $range = $this->resolveDateRange($request);
        $filters = $this->extractFilters($request);

        $report = $this->service->buildReport($range['start'], $range['end'], $filters);
        $harborData = collect($report['harbors'])->firstWhere('harbor.id', $harbor->id);

        if (!$harborData) {
            return response()->json(['message' => 'Harbor not found'], 404);
        }

        return response()->json([
            'range' => $report['range'],
            'filters' => $report['filters'],
            'benchmark' => $report['benchmark'],
            'harbor' => $harborData,
        ]);
    }

    public function myPerformance(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower((string) $user->role) !== 'partner') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $this->validateRequest($request);
        $range = $this->resolveDateRange($request);
        $filters = $this->extractFilters($request);

        $report = $this->service->buildReport($range['start'], $range['end'], $filters);
        $harborData = collect($report['harbors'])->firstWhere('harbor.id', $user->id);

        if (!$harborData) {
            return response()->json(['message' => 'Harbor not found'], 404);
        }

        return response()->json([
            'range' => $report['range'],
            'filters' => $report['filters'],
            'benchmark' => $report['benchmark'],
            'harbor' => $harborData,
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $rangeDays = (int) $request->query('range_days', 30);

        if ($from && $to) {
            return [
                'start' => $from,
                'end' => $to,
            ];
        }

        $end = now()->toDateString();
        $start = now()->subDays(max(1, $rangeDays))->toDateString();

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function extractFilters(Request $request): array
    {
        return array_filter([
            'device' => $request->query('device'),
            'country' => $request->query('country'),
            'source' => $request->query('source'),
            'medium' => $request->query('medium'),
            'campaign' => $request->query('campaign'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function validateRequest(Request $request): void
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'range_days' => 'nullable|integer|min:1|max:365',
            'device' => 'nullable|string|max:30',
            'country' => 'nullable|string|max:100',
            'source' => 'nullable|string|max:100',
            'medium' => 'nullable|string|max:100',
            'campaign' => 'nullable|string|max:120',
        ]);
    }

    private function ensurePartner(User $harbor): void
    {
        if (strtolower((string) $harbor->role) !== 'partner') {
            abort(404, 'Harbor not found');
        }
    }
}
