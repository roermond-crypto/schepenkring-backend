<?php

namespace App\Http\Controllers;

use App\Models\HarborBookingSetting;
use App\Models\User;
use Illuminate\Http\Request;

class HarborBookingSettingController extends Controller
{
    public function show(User $harbor)
    {
        $this->ensurePartner($harbor);

        $settings = HarborBookingSetting::where('harbor_id', $harbor->id)->first();
        if (!$settings) {
            $settings = new HarborBookingSetting(array_merge(
                HarborBookingSetting::defaultAttributes(),
                ['harbor_id' => $harbor->id]
            ));
        }

        return response()->json([
            'harbor' => [
                'id' => $harbor->id,
                'name' => $harbor->name,
                'email' => $harbor->email,
                'status' => $harbor->status,
            ],
            'settings' => $settings,
        ]);
    }

    public function upsert(User $harbor, Request $request)
    {
        $this->ensurePartner($harbor);

        $existing = HarborBookingSetting::where('harbor_id', $harbor->id)->first();

        $validated = $request->validate([
            'default_duration_minutes' => 'nullable|integer|min:15|max:480',
            'opening_hours_start' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'opening_hours_end' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'slot_step_minutes' => 'nullable|integer|min:5|max:240',
            'max_boats_per_timeslot' => 'nullable|integer|min:1|max:100',
            'max_boats_per_day' => 'nullable|integer|min:1|max:1000',
            'buffer_minutes' => 'nullable|integer|min:0|max:240',
            'min_booking_hours' => 'nullable|integer|min:0|max:720',
            'max_booking_days' => 'nullable|integer|min:1|max:365',
            'count_pending' => 'nullable|boolean',
        ]);

        if ($existing) {
            $settings = $existing;
            $settings->fill($validated);
        } else {
            $payload = array_merge(HarborBookingSetting::defaultAttributes(), $validated);
            $settings = new HarborBookingSetting(array_merge($payload, ['harbor_id' => $harbor->id]));
        }

        $settings->save();

        return response()->json([
            'message' => 'Settings saved',
            'settings' => $settings,
        ]);
    }

    private function ensurePartner(User $harbor): void
    {
        if (strtolower((string) $harbor->role) !== 'partner') {
            abort(404, 'Harbor not found.');
        }
    }
}
