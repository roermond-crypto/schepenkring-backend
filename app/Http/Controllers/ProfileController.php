<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function show()
    {
        return response()->json(Auth::user());
    }

    /**
     * Update the user's profile.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            // Existing fields
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:20480',

            // New personal fields
            'relationNumber' => 'nullable|string|max:50',
            'firstName'     => 'nullable|string|max:100',
            'lastName'      => 'nullable|string|max:100',
            'prefix'        => 'nullable|string|max:20',
            'initials'      => 'nullable|string|max:20',
            'title'         => 'nullable|string|max:50',
            'salutation'    => 'nullable|string|max:50',
            'attentionOf'   => 'nullable|string|max:255',
            'identification'=> 'nullable|string|max:100',
            'dateOfBirth'   => 'nullable|date',
            'website'       => 'nullable|url|max:255',
            'mobile'        => 'nullable|string|max:20',
            'street'        => 'nullable|string|max:255',
            'houseNumber'   => 'nullable|string|max:20',
            'note'          => 'nullable|string',
            'claimHistoryCount' => 'nullable|integer|min:0',

            // Lockscreen & Security
            'lockscreen_timeout' => 'nullable|integer|min:1|max:120',
            'lockscreen_code'    => 'nullable|string|size:4', // 4-digit PIN
            'otp_enabled'        => 'nullable|boolean',       // YES/NO Option
            'notifications_enabled' => 'nullable|boolean',
            'email_notifications_enabled' => 'nullable|boolean',
        ]);

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $path = $request->file('profile_image')->store('profiles', 'public');
            $user->profile_image = $path;
        }

        // Update all fillable fields
        $user->fill($request->only([
            'name', 'email', 'phone_number', 'address', 'city', 'state',
            'postcode', 'country', 'relationNumber', 'firstName', 'lastName',
            'prefix', 'initials', 'title', 'salutation', 'attentionOf',
            'identification', 'dateOfBirth', 'website', 'mobile', 'street',
            'houseNumber', 'note', 'claimHistoryCount',
            'lockscreen_timeout', 'lockscreen_code', 'otp_enabled',
            'notifications_enabled', 'email_notifications_enabled'
        ]));

        // Set default 4-digit code to 1234 if not present
        if (empty($user->lockscreen_code)) {
            $user->lockscreen_code = '1234';
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Change the user's password.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Verify the user's PIN (for lockscreen unlock).
     */
    public function verifyPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();

        // Check against the 4-digit lockscreen_code instead of the main password
        // Default to '1234' if for some reason it's null
        $currentCode = $user->lockscreen_code ?? '1234';

        if ($request->password !== $currentCode) {
            return response()->json([
                'message' => 'Invalid PIN code'
            ], 422);
        }

        return response()->json([
            'message' => 'Unlocked successfully'
        ]);
    }
}
