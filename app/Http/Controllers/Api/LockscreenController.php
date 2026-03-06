<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LockscreenController extends Controller
{
    /**
     * Verify the user's PIN (for lockscreen unlock).
     */
    public function verifyPin(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        // Check against the 4-digit lockscreen_code
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
