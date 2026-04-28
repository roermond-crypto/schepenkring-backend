<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\IntegrationAccessDetailsMail;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class IntegrationController extends Controller
{
    /**
     * List all integrations (secrets masked).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Integration::query()->orderBy('integration_type')->orderBy('environment');

        if ($request->filled('integration_type')) {
            $query->where('integration_type', $request->integration_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('environment')) {
            $query->where('environment', $request->environment);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        return response()->json(
            $query->get()->map(fn ($i) => $this->masked($i))
        );
    }

    /**
     * Show a single integration (secrets masked).
     */
    public function show(int $id): JsonResponse
    {
        $integration = Integration::findOrFail($id);

        return response()->json($this->masked($integration));
    }

    /**
     * Create a new integration.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'integration_type' => 'required|string|max:100',
            'label'            => 'nullable|string|max:255',
            'username'         => 'nullable|string|max:255',
            'password'         => 'nullable|string|max:1000',
            'api_key'          => 'nullable|string|max:2000',
            'environment'      => 'sometimes|in:test,live',
            'status'           => 'sometimes|in:active,inactive',
            'location_id'      => 'nullable|integer',
        ]);

        $integration = Integration::create([
            'integration_type'   => $validated['integration_type'],
            'label'              => $validated['label'] ?? null,
            'username'           => $validated['username'] ?? null,
            'password_encrypted' => $validated['password'] ?? null,
            'api_key_encrypted'  => $validated['api_key'] ?? null,
            'environment'        => $validated['environment'] ?? 'live',
            'status'             => $validated['status'] ?? 'active',
            'location_id'        => $validated['location_id'] ?? null,
        ]);

        return response()->json($this->masked($integration), 201);
    }

    /**
     * Update an existing integration.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $integration = Integration::findOrFail($id);

        $validated = $request->validate([
            'integration_type' => 'sometimes|string|max:100',
            'label'            => 'nullable|string|max:255',
            'username'         => 'nullable|string|max:255',
            'password'         => 'nullable|string|max:1000',
            'api_key'          => 'nullable|string|max:2000',
            'environment'      => 'sometimes|in:test,live',
            'status'           => 'sometimes|in:active,inactive',
            'location_id'      => 'nullable|integer',
        ]);

        $data = collect($validated)->only([
            'integration_type', 'label', 'username', 'environment', 'status', 'location_id',
        ])->toArray();

        // Only update secrets when a new value is explicitly sent
        if (array_key_exists('password', $validated)) {
            $data['password_encrypted'] = $validated['password'];
        }
        if (array_key_exists('api_key', $validated)) {
            $data['api_key_encrypted'] = $validated['api_key'];
        }

        $integration->update($data);

        return response()->json($this->masked($integration->fresh()));
    }

    /**
     * Delete an integration.
     */
    public function destroy(int $id): JsonResponse
    {
        $integration = Integration::findOrFail($id);
        $integration->delete();

        return response()->json(['message' => 'Integration deleted.']);
    }

    public function sendAccessDetails(Request $request, int $id): JsonResponse
    {
        $integration = Integration::findOrFail($id);
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $allowedEmails = $this->allowedDeliveryEmails();
        if ($allowedEmails === []) {
            throw ValidationException::withMessages([
                'email' => ['No authorized delivery emails are configured for integration access details.'],
            ]);
        }

        $targetEmail = strtolower(trim((string) $validated['email']));
        $normalizedAllowedEmails = array_map(
            static fn (string $email): string => strtolower(trim($email)),
            $allowedEmails,
        );

        if (!in_array($targetEmail, $normalizedAllowedEmails, true)) {
            throw ValidationException::withMessages([
                'email' => ['This email address is not authorized for integration access delivery.'],
            ]);
        }

        Mail::to($targetEmail)->send(new IntegrationAccessDetailsMail($integration, $request->user()));

        Log::info('Integration access details delivered by email.', [
            'integration_id' => $integration->id,
            'integration_type' => $integration->integration_type,
            'recipient_email' => $targetEmail,
            'requested_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Access details sent successfully.',
        ]);
    }

    // ── Private ────────────────────────────────────────

    /**
     * Return a safe array representation with masked secrets.
     */
    private function masked(Integration $integration): array
    {
        return [
            'id'               => $integration->id,
            'integration_type' => $integration->integration_type,
            'label'            => $integration->label,
            'username'         => $integration->username,
            'has_password'     => ! empty($integration->password_encrypted),
            'has_api_key'      => ! empty($integration->api_key_encrypted),
            'environment'      => $integration->environment,
            'status'           => $integration->status,
            'location_id'      => $integration->location_id,
            'created_at'       => $integration->created_at,
            'updated_at'       => $integration->updated_at,
        ];
    }

    private function allowedDeliveryEmails(): array
    {
        $configured = config('services.integrations.access_delivery_allowed_emails', []);

        if (!is_array($configured)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($email): string => trim((string) $email),
            $configured,
        )));
    }
}
