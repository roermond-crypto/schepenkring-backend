<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Events\UserRegistered;
use App\Services\EmailVerificationService;

class QuickAuthController extends Controller
{
    // Every registration through this endpoint becomes a PARTNER
    public function registerPartner(Request $request, EmailVerificationService $emailVerification)
    {
        try {
            $user = User::create([
                'name'         => $request->name,
                'email'        => $request->email,
                'password'     => Hash::make($request->password),
                'role'         => 'Partner',
                'status'       => 'email_pending',
                'access_level' => 'Limited',
            ]);

            $emailVerification->sendCode($user, $request, ['source' => 'quick_register_partner']);

            event(new UserRegistered($user, $user));

            return response()->json([
                'userType' => 'Partner',
                'id' => $user->id,
                'verification_required' => true,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Direct Insert Failed: ' . $e->getMessage()], 500);
        }
    }

    // Every registration here is a standard USER
    public function registerUser(Request $request, EmailVerificationService $emailVerification)
    {
        $validation = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);
        try {
            $user = User::create([
                'name'         => $request->name,
                'email'        => $request->email,
                'password'     => Hash::make($request->password),
                'role'         => 'Customer',
                'status'       => 'email_pending',
                'access_level' => 'None',
            ]);

            $emailVerification->sendCode($user, $request, ['source' => 'quick_register_user']);

            event(new UserRegistered($user, $user));

            return response()->json([
                'userType' => 'Customer',
                'id' => $user->id,
                'verification_required' => true,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Direct Insert Failed: ' . $e->getMessage()], 500);
        }
    }

}
