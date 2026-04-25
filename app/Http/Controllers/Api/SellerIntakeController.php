<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoatIntake;
use App\Models\User;
use App\Services\SellerIntakeWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerIntakeController extends Controller
{
    public function store(Request $request, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $this->ensureIntakeUser($request);
        $intake = $service->getOrCreateDraft($user);
        $validated = $request->validate([
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'year' => 'nullable|integer|min:1900|max:2100',
            'length_m' => 'nullable|numeric|min:0',
            'width_m' => 'nullable|numeric|min:0',
            'height_m' => 'nullable|numeric|min:0',
            'fuel_type' => 'nullable|string|max:80',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:5000',
            'boat_type' => 'nullable|string|max:120',
        ]);

        if (count(array_filter($validated, static fn ($value) => $value !== null && $value !== '')) > 0) {
            $intake = $service->saveIntake($user, $validated, $intake);
        }

        return response()->json([
            'message' => 'Seller intake ready',
            'data' => $service->serializeIntake($intake),
        ], $intake->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $this->ensureIntakeUser($request);
        $intake = BoatIntake::query()->where('user_id', $user->id)->findOrFail($id);

        return response()->json([
            'data' => $service->serializeIntake($intake),
        ]);
    }

    public function update(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $this->ensureIntakeUser($request);
        $intake = BoatIntake::query()->where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:2100',
            'length_m' => 'required|numeric|min:0',
            'width_m' => 'required|numeric|min:0',
            'height_m' => 'required|numeric|min:0',
            'fuel_type' => 'required|string|max:80',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:5000',
            'boat_type' => 'nullable|string|max:120',
        ]);

        $intake = $service->saveIntake($user, $validated, $intake);

        return response()->json([
            'message' => 'Seller intake saved',
            'data' => $service->serializeIntake($intake),
        ]);
    }

    public function uploadPhotos(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $this->ensureIntakeUser($request);
        $intake = BoatIntake::query()->where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'photos' => 'required|array|min:1',
            'photos.*' => 'required|file|image|max:10240',
        ]);

        $intake = $service->addPhotos($intake, $validated['photos']);

        return response()->json([
            'message' => 'Photos uploaded',
            'data' => $service->serializeIntake($intake),
        ]);
    }

    public function paymentSession(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $this->ensureIntakeUser($request);
        $intake = BoatIntake::query()->where('user_id', $user->id)->findOrFail($id);

        foreach (['brand', 'model', 'year', 'length_m', 'width_m', 'height_m', 'fuel_type'] as $field) {
            if (blank($intake->{$field})) {
                return response()->json([
                    'message' => 'Brand, model, year, dimensions, and fuel type must be completed before checkout.',
                ], 422);
            }
        }

        if (count($intake->photo_manifest_json ?? []) < 1) {
            return response()->json(['message' => 'At least one photo is required before checkout.'], 422);
        }

        $validated = $request->validate([
            'redirect_url' => 'required|url|max:500',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        try {
            $payment = $service->createPaymentSession($intake, $validated);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'payment' => $payment,
            'checkout_url' => $payment->checkout_url,
            'data' => $service->serializeIntake($intake->fresh(['latestPayment', 'listingWorkflow'])),
        ]);
    }

    public function paymentStatus(Request $request, int $id, SellerIntakeWorkflowService $service): JsonResponse
    {
        $user = $this->ensureIntakeUser($request);
        $intake = BoatIntake::query()->where('user_id', $user->id)->findOrFail($id);

        return response()->json([
            'payment' => $intake->latestPayment,
            'data' => $service->serializeIntake($intake->fresh(['latestPayment', 'listingWorkflow'])),
        ]);
    }

    private function ensureIntakeUser(Request $request): User
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401, 'Unauthorized.');

        $role = strtolower((string) $user->role);
        $allowed = $user->isClient()
            || $user->isStaff()
            || in_array($role, ['client', 'seller', 'buyer'], true);

        abort_unless($allowed, 403, 'Client account required.');

        return $user;
    }
}
