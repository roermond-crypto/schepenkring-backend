<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower($request->email);
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user) {
            return response()->json(['message' => 'If your email is in our system, a password reset link has been sent.'], 200);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now()
        ]);

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $link = rtrim($frontendUrl, '/') . "/nl/auth/reset-password?token={$token}&email=" . urlencode($email);

        Mail::raw("You are receiving this email because we received a password reset request for your account.\n\nReset Password Link: {$link}\n\nIf you did not request a password reset, no further action is required.", function ($message) use ($email) {
            $message->to($email)
                    ->subject('Reset Password Notification');
        });

        Log::info("Password reset requested for {$email}");

        return response()->json(['message' => 'Reset link sent successfully.'], 200);
    }

    public function verifyToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);
        $email = strtolower($request->email);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Invalid or expired password reset link.'], 400);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json(['message' => 'Password reset link has expired.'], 400);
        }

        return response()->json(['message' => 'Token is valid.'], 200);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|min:8',
            'password_confirmation' => 'required|same:password'
        ]);
        $email = strtolower($request->email);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Invalid or expired password reset link.'], 400);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json(['message' => 'Password reset link has expired.'], 400);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        Log::info("Password has been successfully reset for {$email}");

        return response()->json(['message' => 'Password has been successfully reset.'], 200);
    }
}
