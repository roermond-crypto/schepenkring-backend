<?php

namespace App\Http\Controllers\Api\RetellTool;

use App\Http\Controllers\Controller;
use App\Mail\TemplatedMail;
use App\Models\BoatIntake;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActivityFeedService;
use App\Services\EmailTemplateResolver;
use App\Services\FollowUpService;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Mutating tool endpoints — every one of these goes through
 * withIdempotency() since a retried Retell tool call must never create a
 * second appointment/callback/email.
 */
class RetellToolActionController extends Controller
{
    use RetellToolHelpers;

    public function __construct(
        private FollowUpService $followUps,
        private ActivityFeedService $activityFeed,
        private EmailTemplateResolver $templates,
    ) {
    }

    public function createAppointment(Request $request, IdempotencyService $idempotency): JsonResponse
    {
        $validated = $request->validate([
            'yacht_id' => 'required|integer',
            'location_id' => 'required|integer',
            'date' => 'required|date',
            'time' => 'required',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        return $this->withIdempotency($request, $idempotency, function () use ($validated, $request) {
            $this->assertLocationScope((int) $validated['location_id'], $this->resolveCallSession($request));

            $booking = Booking::create([
                'location_id' => $validated['location_id'],
                'boat_id' => $validated['yacht_id'],
                'type' => $validated['type'] ?? 'viewing',
                'status' => 'requested',
                'date' => $validated['date'],
                'time' => $validated['time'],
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'source' => 'retell_voice',
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->activityFeed->record('yacht', $validated['yacht_id'], 'call.appointment.created', "Viewing requested via voice call for {$validated['name']}", [
                'booking_id' => $booking->id,
            ]);

            return response()->json(['created' => true, 'booking_id' => $booking->id]);
        });
    }

    public function createCallback(Request $request, IdempotencyService $idempotency): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email',
            'callback_at' => 'nullable|date',
            'reason' => 'nullable|string',
            'yacht_id' => 'nullable|integer',
        ]);

        return $this->withIdempotency($request, $idempotency, function () use ($validated, $request) {
            $this->assertLocationScope((int) $validated['location_id'], $this->resolveCallSession($request));

            $lead = Lead::create([
                'location_id' => $validated['location_id'],
                'yacht_id' => $validated['yacht_id'] ?? null,
                'status' => 'callback_requested',
                'source' => 'retell_voice',
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'notes' => $validated['reason'] ?? null,
            ]);

            $followUp = $this->followUps->applyOutcome('lead', $lead->id, 'callback_requested', [
                'callback_at' => $validated['callback_at'] ?? null,
                'related_yacht_id' => $validated['yacht_id'] ?? null,
                'ai_summary' => $validated['reason'] ?? null,
            ]);

            return response()->json(['created' => true, 'lead_id' => $lead->id, 'follow_up_id' => $followUp?->id]);
        });
    }

    public function sendOnboardingLink(Request $request, IdempotencyService $idempotency): JsonResponse
    {
        $validated = $request->validate(['seller_id' => 'required|integer']);

        return $this->withIdempotency($request, $idempotency, function () use ($validated) {
            $seller = User::find($validated['seller_id']);
            if (! $seller || ! $seller->email) {
                return response()->json(['sent' => false, 'error' => 'seller_not_found_or_no_email']);
            }

            $frontendBase = rtrim((string) config('app.frontend_url', 'https://schepenkring.nl'), '/');
            $locale = $seller->locale ?? 'nl';
            $onboardingUrl = "{$frontendBase}/{$locale}/boot-aanmelden";

            $rendered = $this->templates->resolveAndRender('onboarding_link_reminder', $seller->client_location_id, $locale, [
                'user_name' => $seller->name,
                'onboarding_url' => $onboardingUrl,
            ]);

            if ($rendered === null) {
                return response()->json(['sent' => false, 'error' => 'no_email_template']);
            }

            Mail::to($seller->email)->queue(TemplatedMail::fromResolved($rendered));

            $this->activityFeed->record('user', $seller->id, 'call.onboarding_link.sent', 'Onboarding link sent via voice call');

            return response()->json(['sent' => true]);
        });
    }

    public function onboardingStatus(Request $request): JsonResponse
    {
        $validated = $request->validate(['seller_id' => 'required|integer']);

        return $this->safe(function () use ($validated) {
            $intake = BoatIntake::where('seller_user_id', $validated['seller_id'])->latest('id')->first();
            if (! $intake) {
                return response()->json(['found' => false]);
            }

            return response()->json([
                'found' => true,
                'status' => $intake->status,
                'intake_score' => $intake->intake_score,
                'missing_items' => $intake->missing_items,
            ]);
        });
    }
}
