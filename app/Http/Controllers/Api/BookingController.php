<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Location;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function availability(Request $request, $locationId)
    {
        $request->validate([
            'date' => 'required|date',
            'duration' => 'nullable|integer',
        ]);

        $date = Carbon::parse($request->input('date'))->format('Y-m-d');
        // Simple mock availability for now, ideally tied to business logic
        $allSlots = ['09:00', '10:30', '13:00', '14:30', '16:00'];
        
        $bookedSlots = Booking::where('location_id', $locationId)
            ->where('date', $date)
            ->where('status', '!=', 'cancelled')
            ->pluck('time')
            ->map(function ($time) {
                return Carbon::parse($time)->format('H:i');
            })
            ->toArray();

        $availableSlots = array_values(array_diff($allSlots, $bookedSlots));

        return response()->json([
            'date' => $date,
            'available_slots' => $availableSlots,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'required|integer|exists:locations,id',
            'boat_id' => 'nullable|integer|exists:yachts,id',
            'type' => 'nullable|string',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'name' => 'nullable|string',
            'email' => 'nullable|email',
            'source' => 'nullable|string',
            'notes' => 'nullable|string',
            'conversation_id' => 'nullable|uuid|exists:conversations,id',
        ]);

        // If user is authenticated, resolve identity
        if ($request->user()) {
            $validated['name'] = $validated['name'] ?? $request->user()->name;
            $validated['email'] = $validated['email'] ?? $request->user()->email;
        }

        $validated['duration_minutes'] = 60; // Default duration
        $validated['status'] = 'confirmed'; // Auto-confirming by default for unified logic

        $booking = Booking::create($validated);

        if (!empty($validated['conversation_id'])) {
            try {
                $conversation = \App\Models\Conversation::find($validated['conversation_id']);
                if ($conversation) {
                    $boatName = '';
                    if (!empty($validated['boat_id'])) {
                        $boat = \App\Models\Yacht::find($validated['boat_id']);
                        if ($boat) {
                            $boatName = " for " . $boat->boat_name;
                        }
                    }
                    $typeStr = $validated['type'] ?? 'viewing';
                    $dateFormatted = Carbon::parse($validated['date'])->format('F jS');
                    $timeFormatted = $validated['time'];
                    $nameStr = $validated['name'] ?? 'A visitor';
                    
                    $text = "{$nameStr} requested a {$typeStr} booking{$boatName} on {$dateFormatted} at {$timeFormatted}.";

                    $service = app(\App\Services\ChatConversationService::class);
                    $service->addMessage($conversation, [
                        'sender_type' => 'system',
                        'message_type' => 'event',
                        'text' => $text,
                        'metadata' => ['booking_id' => $booking->id]
                    ], $request, $request->user());
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to attach booking to conversation: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Booking confirmed',
            'booking' => $booking,
        ], 201);
    }
}
