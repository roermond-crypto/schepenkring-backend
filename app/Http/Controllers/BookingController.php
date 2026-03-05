<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\HarborBookingSetting;
use App\Models\HarborChatSetting;
use App\Models\Bid;
use App\Models\Task;
use App\Models\Yacht;
use Illuminate\Support\Facades\DB;
use App\Events\TaskCreated;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function getAvailableSlots(Request $request, $id)
    {
        $dateParam = $request->query('date');
        if (!$dateParam) {
            return response()->json(['timeSlots' => []]);
        }

        $yacht = Yacht::findOrFail($id);
        $harborId = $this->resolveHarborId($yacht);
        $settings = $this->resolveBookingSettings($harborId);
        $timezone = $this->resolveBookingTimezone($harborId);
        $date = Carbon::parse($dateParam, $timezone)->startOfDay();

        $duration = $yacht->booking_duration_minutes ?: $settings->default_duration_minutes;
        $slotStep = max(1, (int) ($settings->slot_step_minutes ?: 15));
        $buffer = max(0, (int) ($settings->buffer_minutes ?? 0));
        $slotLimit = max(1, (int) ($settings->max_boats_per_timeslot ?? 1));

        $now = Carbon::now($timezone);
        $minDateTime = $now->copy()->addHours((int) ($settings->min_booking_hours ?? 0));
        $maxDateTime = $now->copy()->addDays((int) ($settings->max_booking_days ?? 30))->endOfDay();

        if ($date->greaterThan($maxDateTime) || $date->copy()->endOfDay()->lessThan($minDateTime)) {
            return response()->json(['timeSlots' => []]);
        }

        $openStart = Carbon::parse($date->toDateString() . ' ' . $settings->opening_hours_start, $timezone);
        $openEnd = Carbon::parse($date->toDateString() . ' ' . $settings->opening_hours_end, $timezone);

        if ($openEnd->lessThanOrEqualTo($openStart)) {
            return response()->json(['timeSlots' => []]);
        }

        $countStatuses = $settings->count_pending ? ['confirmed', 'pending'] : ['confirmed'];

        $bookings = $this->bookingQueryForDate($date, $harborId, $yacht->id, $countStatuses)->get([
            'id',
            'yacht_id',
            'harbor_id',
            'start_at',
            'end_at',
            'status',
        ]);

        $dailyLimit = (int) ($settings->max_boats_per_day ?? 0);
        if ($dailyLimit > 0 && $bookings->count() >= $dailyLimit) {
            return response()->json(['timeSlots' => []]);
        }

        $slots = [];
        $current = $openStart->copy();

        while ($current->copy()->addMinutes($duration + $buffer) <= $openEnd) {
            if ($current->lessThan($minDateTime)) {
                $current->addMinutes($slotStep);
                continue;
            }

            $slotEnd = $current->copy()->addMinutes($duration + $buffer);
            $overlapCount = 0;
            foreach ($bookings as $booking) {
                if ($current < $booking->end_at && $slotEnd > $booking->start_at) {
                    $overlapCount++;
                    if ($overlapCount >= $slotLimit) {
                        break;
                    }
                }
            }

            if ($overlapCount < $slotLimit) {
                $slots[] = $current->format('H:i');
            }

            $current->addMinutes($slotStep);
        }

        return response()->json(['timeSlots' => $slots]);
    }

public function getAvailableDates(Request $request, $id)
{
    $month = $request->query('month');
    $year = $request->query('year');
    
    if (!$month || !$year) {
        return response()->json(['availableDates' => []]);
    }

    $yacht = Yacht::findOrFail($id);
    $harborId = $this->resolveHarborId($yacht);
    $settings = $this->resolveBookingSettings($harborId);
    $timezone = $this->resolveBookingTimezone($harborId);

    $countStatuses = $settings->count_pending ? ['confirmed', 'pending'] : ['confirmed'];

    $monthStart = Carbon::createFromDate($year, $month, 1, $timezone)->startOfMonth();
    $monthEnd = $monthStart->copy()->endOfMonth();

    $now = Carbon::now($timezone);
    $minDate = $now->copy()->addHours((int) ($settings->min_booking_hours ?? 0))->startOfDay();
    $maxDate = $now->copy()->addDays((int) ($settings->max_booking_days ?? 30))->endOfDay();

    $dailyLimit = (int) ($settings->max_boats_per_day ?? 0);
    $dailyCounts = [];
    if ($dailyLimit > 0) {
        $dailyCounts = $this->bookingQueryForRange($monthStart, $monthEnd, $harborId, $yacht->id, $countStatuses)
            ->selectRaw('DATE(start_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();
    }

    $availableDates = [];
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = Carbon::createFromDate($year, $month, $day, $timezone)->startOfDay();

        if ($date->lessThan($minDate) || $date->greaterThan($maxDate)) {
            continue;
        }

        if ($dailyLimit > 0) {
            $count = (int) ($dailyCounts[$date->toDateString()] ?? 0);
            if ($count >= $dailyLimit) {
                continue;
            }
        }

        $availableDates[] = $date->toDateString();
    }

    // Wrap in the 'availableDates' key that your frontend expects
    return response()->json(['availableDates' => $availableDates]);
}

public function storeBooking(Request $request, $id)
{
    $user = $request->user();
    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $winningBid = Bid::where('yacht_id', $id)
        ->where('status', 'won')
        ->first();

    if (!$winningBid || $winningBid->user_id !== $user->id) {
        return response()->json(['error' => 'Only the winning bidder can book an appointment'], 403);
    }

    $request->validate([
        'start_at' => 'required|date|after:now',
    ]);

    $start = Carbon::parse($request->start_at);

    $yacht = Yacht::findOrFail($id);
    $harborId = $this->resolveHarborId($yacht);
    $settings = $this->resolveBookingSettings($harborId);
    $timezone = $this->resolveBookingTimezone($harborId);

    $duration = $yacht->booking_duration_minutes ?: $settings->default_duration_minutes;
    $buffer = max(0, (int) ($settings->buffer_minutes ?? 0));
    $end = $start->copy()->addMinutes($duration + $buffer);

    $startForValidation = $start->copy()->setTimezone($timezone);
    $endForValidation = $end->copy()->setTimezone($timezone);
    $openStart = Carbon::parse($startForValidation->toDateString() . ' ' . $settings->opening_hours_start, $timezone);
    $openEnd = Carbon::parse($startForValidation->toDateString() . ' ' . $settings->opening_hours_end, $timezone);

    if ($startForValidation->lessThan($openStart) || $endForValidation->greaterThan($openEnd)) {
        return response()->json(['error' => 'Outside opening hours'], 422);
    }

    $now = Carbon::now($timezone);
    $minDateTime = $now->copy()->addHours((int) ($settings->min_booking_hours ?? 0));
    $maxDateTime = $now->copy()->addDays((int) ($settings->max_booking_days ?? 30))->endOfDay();

    if ($startForValidation->lessThan($minDateTime) || $startForValidation->greaterThan($maxDateTime)) {
        return response()->json(['error' => 'Booking time not allowed'], 422);
    }

    $countStatuses = $settings->count_pending ? ['confirmed', 'pending'] : ['confirmed'];
    $dailyLimit = (int) ($settings->max_boats_per_day ?? 0);
    $slotLimit = max(1, (int) ($settings->max_boats_per_timeslot ?? 1));

    $booking = DB::transaction(function () use (
        $start,
        $end,
        $id,
        $user,
        $yacht,
        $harborId,
        $countStatuses,
        $dailyLimit,
        $slotLimit,
        $winningBid
    ) {
        $baseQuery = $this->bookingQueryForDate($start->copy()->startOfDay(), $harborId, $id, $countStatuses)
            ->lockForUpdate();

        if ($dailyLimit > 0) {
            $dailyCount = (clone $baseQuery)->count();
            if ($dailyCount >= $dailyLimit) {
                return null;
            }
        }

        $overlapCount = (clone $baseQuery)
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->count();

        if ($overlapCount >= $slotLimit) {
            return null;
        }

        return Booking::create([
            'yacht_id' => $id,
            'harbor_id' => $harborId,
            'user_id' => $user->id,
            'seller_user_id' => $yacht->user_id,
            'bid_id' => $winningBid->id,
            'start_at' => $start,
            'end_at' => $end,
            'location' => $yacht->where ?? null,
            'type' => 'appointment',
            'status' => 'confirmed',
        ]);
    });

    if (!$booking) {
        return response()->json(['error' => 'Slot no longer available'], 422);
    }

    $task = Task::create([
        'title' => 'Attend viewing – ' . ($yacht->boat_name ?? "Yacht {$yacht->id}"),
        'description' => 'Appointment booked for yacht viewing/trial.',
        'priority' => 'Medium',
        'status' => 'New',
        'assignment_status' => 'pending',
        'assigned_to' => $user->id,
        'user_id' => $user->id,
        'created_by' => $user->id,
        'yacht_id' => $yacht->id,
        'appointment_id' => $booking->id,
        'due_date' => $start->toDateString(),
        'type' => 'appointment',
    ]);

    event(new TaskCreated($task, $user));

    event(new BookingCreated($booking, $request->user()));

    return response()->json($booking, 201);
}

public function myAppointments(Request $request)
{
    $user = $request->user();
    if (!$user) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $appointments = \App\Models\Booking::with('yacht:id,boat_name')
        ->where('user_id', $user->id)
        ->orderBy('start_at', 'asc')
        ->get();

    return response()->json($appointments);
}

public function adminStoreAppointment(Request $request)
{
    $user = $request->user();
    if (!$user || !in_array(strtolower((string) $user->role), ['admin', 'employee'])) {
        return response()->json(['error' => 'Forbidden'], 403);
    }

    $validated = $request->validate([
        'yacht_id' => 'required|integer|exists:yachts,id',
        'start_at' => 'required|date|after:now',
        'type' => 'nullable|string|max:100',
        'location' => 'nullable|string|max:255',
        'status' => 'nullable|in:pending,confirmed,cancelled',
    ]);

    $start = Carbon::parse($validated['start_at']);
    $yacht = Yacht::findOrFail((int) $validated['yacht_id']);
    $harborId = $this->resolveHarborId($yacht);
    $settings = $this->resolveBookingSettings($harborId);
    $timezone = $this->resolveBookingTimezone($harborId);

    $duration = $yacht->booking_duration_minutes ?: $settings->default_duration_minutes;
    $buffer = max(0, (int) ($settings->buffer_minutes ?? 0));
    $end = $start->copy()->addMinutes($duration + $buffer);

    $startForValidation = $start->copy()->setTimezone($timezone);

    $now = Carbon::now($timezone);
    $minDateTime = $now->copy()->addHours((int) ($settings->min_booking_hours ?? 0));
    $maxDateTime = $now->copy()->addDays((int) ($settings->max_booking_days ?? 30))->endOfDay();

    if ($startForValidation->lessThan($minDateTime) || $startForValidation->greaterThan($maxDateTime)) {
        return response()->json(['error' => 'Booking time not allowed'], 422);
    }

    $status = $validated['status'] ?? 'confirmed';
    $type = $validated['type'] ?? 'appointment';
    $location = $validated['location'] ?? ($yacht->where ?? null);

    $booking = DB::transaction(function () use (
        $start,
        $end,
        $yacht,
        $user,
        $harborId,
        $status,
        $type,
        $location
    ) {
        return Booking::create([
            'yacht_id' => $yacht->id,
            'harbor_id' => $harborId,
            'user_id' => $user->id,
            'seller_user_id' => $yacht->user_id,
            'bid_id' => null,
            'deal_id' => null,
            'start_at' => $start,
            'end_at' => $end,
            'location' => $location,
            'type' => $type,
            'status' => $status,
        ]);
    });

    return response()->json(
        $booking->load(['yacht:id,boat_name', 'user:id,name,email', 'seller:id,name,email']),
        201
    );
}

public function adminAppointments(Request $request)
{
    $user = $request->user();
    if (!$user || !in_array(strtolower($user->role), ['admin', 'employee'])) {
        return response()->json(['error' => 'Forbidden'], 403);
    }

    $query = \App\Models\Booking::with(['yacht:id,boat_name', 'user:id,name,email', 'seller:id,name,email']);

    if ($request->filled('yacht_id')) {
        $query->where('yacht_id', $request->yacht_id);
    }
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }
    if ($request->filled('from') && $request->filled('to')) {
        $query->whereBetween('start_at', [$request->from, $request->to]);
    }

    return response()->json($query->orderBy('start_at', 'asc')->get());
}

public function boatAppointments(Request $request, $id)
{
    $user = $request->user();
    if (!$user || !in_array(strtolower($user->role), ['admin', 'employee'])) {
        return response()->json(['error' => 'Forbidden'], 403);
    }

    $appointments = \App\Models\Booking::with(['user:id,name,email', 'seller:id,name,email'])
        ->where('yacht_id', $id)
        ->orderBy('start_at', 'asc')
        ->get();

    return response()->json($appointments);
}

    private function resolveHarborId(Yacht $yacht): ?int
    {
        if (!empty($yacht->user_id)) {
            return (int) $yacht->user_id;
        }

        if (!empty($yacht->ref_harbor_id)) {
            return (int) $yacht->ref_harbor_id;
        }

        return null;
    }

    private function resolveBookingSettings(?int $harborId): HarborBookingSetting
    {
        if ($harborId) {
            $settings = HarborBookingSetting::where('harbor_id', $harborId)->first();
            if ($settings) {
                $defaults = HarborBookingSetting::defaultAttributes();
                foreach ($defaults as $key => $value) {
                    if ($settings->{$key} === null) {
                        $settings->{$key} = $value;
                    }
                }
                return $settings;
            }
        }

        return new HarborBookingSetting(array_merge(
            HarborBookingSetting::defaultAttributes(),
            ['harbor_id' => $harborId]
        ));
    }

    private function resolveBookingTimezone(?int $harborId): string
    {
        $fallback = (string) env('BOOKING_BUSINESS_TZ', env('CHAT_BUSINESS_TZ', 'Europe/Amsterdam'));
        if ($harborId) {
            $timezone = HarborChatSetting::where('harbor_id', $harborId)->value('timezone');
            if (is_string($timezone) && $timezone !== '') {
                return $this->sanitizeTimezone($timezone, $fallback);
            }
        }

        return $this->sanitizeTimezone($fallback, 'Europe/Amsterdam');
    }

    private function sanitizeTimezone(string $timezone, string $fallback): string
    {
        try {
            new \DateTimeZone($timezone);
            return $timezone;
        } catch (\Throwable $e) {
            try {
                new \DateTimeZone($fallback);
                return $fallback;
            } catch (\Throwable $e) {
                return 'Europe/Amsterdam';
            }
        }
    }

    private function bookingQueryForDate(Carbon $date, ?int $harborId, ?int $yachtId, array $statuses)
    {
        $query = Booking::query()
            ->whereDate('start_at', $date->toDateString())
            ->whereIn('status', $statuses);

        if ($harborId) {
            $query->where('harbor_id', $harborId);
        } elseif ($yachtId) {
            $query->where('yacht_id', $yachtId);
        }

        return $query;
    }

    private function bookingQueryForRange(Carbon $start, Carbon $end, ?int $harborId, ?int $yachtId, array $statuses)
    {
        $query = Booking::query()
            ->whereBetween('start_at', [$start, $end])
            ->whereIn('status', $statuses);

        if ($harborId) {
            $query->where('harbor_id', $harborId);
        } elseif ($yachtId) {
            $query->where('yacht_id', $yachtId);
        }

        return $query;
    }
}
