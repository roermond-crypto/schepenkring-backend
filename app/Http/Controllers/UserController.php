<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Traits\LogsActivity;
use App\Models\UserDevice;
use App\Models\Notification;
use App\Services\CaptchaService;
use App\Services\DeviceInfoService;
use App\Services\EmailVerificationService;
use App\Services\LoginAttemptService;
use App\Services\OtpService;
use App\Services\SessionDeviceService;
use App\Services\SystemLogService;
use Illuminate\Support\Facades\RateLimiter;


class UserController extends Controller
{
    
    use LogsActivity;
    /**
     * Display the Global Directory of users.
     */
    public function index()
    {
        // Eager load permissions to avoid the 500/Slow loading errors [cite: 11]
        $users = User::with(['permissions', 'partnerProfile'])->orderBy('name', 'asc')->get();
        // Use a collection map to ensure PHP treats each item as a User model
        $users->transform(function ($user) {
            /** @var \App\Models\User $user */
            // We set 'permissions' to match the frontend 'permissions?: string[]' interface [cite: 6]
            $user->permissions = $user->getPermissionNames(); 
            return $user;
        });
        return response()->json($users);
    }

    /**
     * Register a new Staff Member or Customer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|in:Admin,Employee,Customer,Partner', // Added Partner [cite: 6]
            'status' => 'required|in:Active,Suspended',
            'access_level' => 'required|in:Full,Limited,None',
        ]);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
            'access_level' => $validated['access_level'],
        ]);
        return response()->json($user, 201);
    }

    /**
     * Display a specific identity's data.
     */
    public function show(User $user)
    {
        $user->load('partnerProfile');
        return response()->json($user);
    }

    /**
     * Update Terminal Commands (Role, Access, Status).
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => 'sometimes|in:Admin,Employee,Customer,Partner', // Added Partner [cite: 13]
            'status' => 'sometimes|in:Active,Suspended',
            'access_level' => 'sometimes|in:Full,Limited,None',
            'password' => 'sometimes|min:8',
        ]);
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Terminate Account (Delete from system).
     */
public function destroy(User $user)
{
    // Security: Prevent self-deletion
    if (Auth::id() === $user->id) {
        return response()->json(['message' => 'Cannot terminate your own session.'], 403);
    }

    $user->delete();
    return response()->json(['message' => 'User deleted successfully']);
}

    /**
     * Toggle status quickly (Active/Suspended).
     */
    public function toggleStatus(User $user)
    {
        $user->status = ($user->status === 'Active') ?
        'Suspended' : 'Active';
        $user->save();

        return response()->json($user);
    }

    public function togglePermission(Request $request, User $user)
    {
        $request->validate([
            'permission' => 'required|string|exists:permissions,name'
        ]);
        $permission = $request->permission;

        if ($user->hasPermissionTo($permission)) {
            $user->revokePermissionTo($permission);
            $status = 'detached';
        } else {
            $user->givePermissionTo($permission);
            $status = 'attached';
        }

        $user->refresh();
        SystemLogService::log(
            'permission_toggled',
            'User',
            $user->id,
            null,
            ['permission' => $permission, 'status' => $status],
            "Permission {$permission} {$status} for {$user->email}",
            $request
        );
        return response()->json([
            'status' => $status,
            'current_permissions' => $user->getPermissionNames(), 
            'message' => "Permission " . ($status === 'attached' ? 'granted' : 'revoked')
        ]);
    }

// In UserController.php - update login method

public function login(
    Request $request,
    LoginAttemptService $attempts,
    DeviceInfoService $deviceInfo,
    SessionDeviceService $sessions,
    OtpService $otpService,
    CaptchaService $captcha,
    \App\Services\OnboardingService $onboarding
)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $email = strtolower($credentials['email']);
    $deviceId = $deviceInfo->resolveDeviceId($request);
    $location = $deviceInfo->resolveLocation($request);
    $asn = $location['asn'] ?? null;

    if ($attempts->isSoftLocked($email, $request->ip(), $asn)) {
        SystemLogService::logAuthSoftLock($email, null, $request, [
            'ip' => $request->ip(),
            'device_id' => $deviceId,
        ]);
        return response()->json([
            'message' => 'Too many attempts. Try again later.',
        ], 429);
    }

    $delaySeconds = $attempts->delaySeconds($email, $request->ip(), $asn);
    if ($delaySeconds > 0) {
        usleep($delaySeconds * 1000000);
    }

    if ($attempts->requiresCaptcha($email, $request->ip(), $asn)) {
        $captchaToken = $request->input('captcha_token');
        if (!$captcha->verify($captchaToken, $request->ip())) {
            $attempts->registerFailedAttempt($email, $request->ip(), $asn);
            SystemLogService::logAuthFailure($email, null, $request, [
                'reason' => 'captcha_failed',
                'ip' => $request->ip(),
                'device_id' => $deviceId,
            ]);
            return response()->json([
                'message' => 'Captcha verification required.',
            ], 422);
        }
    }
    
    if (!Auth::attempt($credentials)) {
        $count = $attempts->registerFailedAttempt($email, $request->ip(), $asn);
        SystemLogService::logAuthFailure($email, null, $request, [
            'ip' => $request->ip(),
            'device_id' => $deviceId,
            'attempts' => $count,
        ]);

        if ($count >= $attempts->softLockAfter()) {
            SystemLogService::logAuthSoftLock($email, null, $request, [
                'ip' => $request->ip(),
                'device_id' => $deviceId,
            ]);
            $notifyKey = 'login:lock:notify:' . sha1($email);
            if (!RateLimiter::tooManyAttempts($notifyKey, 1)) {
                RateLimiter::hit($notifyKey, $attempts->softLockMinutes() * 60);
                Notification::createAndSend(
                    'warning',
                    'Login Soft Lock Triggered',
                    "Soft lock triggered for {$email} from IP {$request->ip()}",
                    []
                );
            }
        }
        return response()->json([
            'message' => 'Identity could not be verified. Check credentials.'
        ], 401);
    }

    $user = Auth::user();

    if (!$user->email_verified_at) {
        Auth::logout();
        return response()->json([
            'message' => 'Email not verified. Please verify to continue.',
            'onboarding_required' => true,
            'next_step' => $onboarding->nextStep($user),
        ], 403);
    }

    $status = strtolower((string) $user->status);
    $onboardingRequired = false;
    if ($status !== '' && $status !== 'active') {
        if (in_array($status, ['pending_agreement', 'contract_pending'], true)) {
            $onboardingRequired = true;
        } else {
            Auth::logout();
            return response()->json([
                'message' => 'Account is not active.',
                'onboarding_required' => true,
                'next_step' => $onboarding->nextStep($user),
            ], 403);
        }
    }

    if ($sessions->isDeviceBlocked($user, $deviceId)) {
        Auth::logout();
        SystemLogService::logDeviceEvent('blocked_login', $user, $request, [
            'device_id' => $deviceId,
        ]);
        return response()->json([
            'message' => 'This device is blocked. Contact support to continue.',
        ], 403);
    }

    $context = $sessions->buildContext($request, $deviceId);
    $stepUpReasons = [];

    $existingDevice = UserDevice::where('user_id', $user->id)
        ->where('device_id', $deviceId)
        ->first();

    if (!$existingDevice && config('security.login.step_up_on_new_device', true)) {
        $stepUpReasons[] = 'new_device';
    }

    $lastDevice = UserDevice::where('user_id', $user->id)
        ->orderByDesc('last_seen_at')
        ->first();

    $currentCountry = $context['ip_country'] ?? null;
    $lastCountry = $lastDevice?->last_ip_country;
    if ($currentCountry && $lastCountry && $currentCountry !== $lastCountry) {
        if (config('security.login.step_up_on_new_country', true)) {
            $stepUpReasons[] = 'new_country';
        }

        $impossibleWindow = (int) config('security.login.impossible_travel_minutes', 120);
        if ($lastDevice?->last_seen_at && $lastDevice->last_seen_at->diffInMinutes(now()) < $impossibleWindow) {
            $stepUpReasons[] = 'impossible_travel';
        }
    }

    if ($attempts->shouldRequireStepUp($email, $request->ip())) {
        $stepUpReasons[] = 'suspicious_pattern';
    }

    if ($user->otp_enabled) {
        $stepUpReasons[] = 'otp_enabled';
    }

    if (!empty($stepUpReasons)) {
        $attempts->clearForSuccess($email, $request->ip(), $asn);
        $rateKey = 'otp:send:' . $user->id . ':' . $request->ip();
        $max = (int) config('security.otp.max_send_per_window', 3);
        $window = (int) config('security.otp.send_window_minutes', 15);
        $overLimit = RateLimiter::tooManyAttempts($rateKey, $max);
        if ($overLimit) {
            SystemLogService::logOtpEvent('send_rate_limited', $user, $request, [
                'rate_key' => $rateKey,
                'window_minutes' => $window,
                'max_per_window' => $max,
            ]);
        }
        RateLimiter::hit($rateKey, $window * 60);

        $challengeData = $otpService->createChallenge($user, 'login', $request, [
            'device_id' => $deviceId,
            'reasons' => $stepUpReasons,
        ]);

        SystemLogService::logOtpEvent('sent', $user, $request, [
            'challenge_id' => $challengeData['challenge']->id,
            'device_id' => $deviceId,
            'reasons' => $stepUpReasons,
        ]);

        Auth::logout();
        return response()->json([
            'step_up_required' => true,
            'otp_challenge_id' => $challengeData['challenge']->id,
            'otp_ttl_minutes' => $challengeData['ttl_minutes'],
            'device_id' => $deviceId,
            'reasons' => array_values(array_unique($stepUpReasons)),
            'message' => 'Verification code sent to your email.',
        ], 202);
    }
    
    // Log the login using our new SystemLog
    \App\Services\SystemLogService::logUserLogin($user, $request);
    
    $attempts->clearForSuccess($email, $request->ip(), $asn);

    $tokenData = $sessions->createToken($user, 'password', $context);
    $token = $tokenData['plainTextToken'];
    SystemLogService::logSessionEvent('created', $user, $request, [
        'device_id' => $deviceId,
        'auth_strength' => 'password',
    ]);
    
    return response()->json([
        'token' => $token,
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'userType' => $user->role,
        'status' => $user->status,
        'access_level' => $user->access_level,
        'permissions' => $user->getPermissionNames(),
        'device_id' => $deviceId,
        'onboarding_required' => $onboardingRequired,
        'next_step' => $onboardingRequired ? $onboarding->nextStep($user) : null,
    ]);
}

// Add logout method
public function logout(Request $request)
{
    $user = $request->user();
    
    if ($user) {
        // Log the logout
        \App\Services\SystemLogService::logUserLogout($user, $request);
        
        // Revoke all tokens
        $user->tokens()->delete();
        SystemLogService::logSessionEvent('revoked_all', $user, $request, []);
    }
    
    return response()->json(['message' => 'Logged out successfully']);
}

    public function getAllPermissions() {
        return response()->json(\Spatie\Permission\Models\Permission::all());
    }

    public function getAllRoles() {
        return response()->json(\Spatie\Permission\Models\Role::all());
    }

public function register(Request $request, EmailVerificationService $emailVerification) 
{
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'accept_terms' => 'accepted' 
        ]);
    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'role' => 'Customer',      
        'status' => 'email_pending',
        'access_level' => 'None',
        'registration_ip' => $request->ip(),
        'user_agent' => $request->header('User-Agent'),
        'terms_accepted_at' => now(),
    ]);

    $user->assignRole('Customer');
    $emailVerification->sendCode($user, $request, ['source' => 'register']);

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'userType' => $user->role, 
        'verification_required' => true,
    ], 201);
}

    /**
     * Register a new Partner identity.
     */
public function registerPartner(Request $request, EmailVerificationService $emailVerification) 
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:8',
        'accept_terms' => 'accepted' 
    ]);

    // SAFETY CHECK: Create the role if it doesn't exist in the DB
    if (!\Spatie\Permission\Models\Role::where('name', 'Partner')->exists()) {
        \Spatie\Permission\Models\Role::create(['name' => 'Partner', 'guard_name' => 'web']);
    }

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'role' => 'Partner', 
        'status' => 'email_pending',
        'access_level' => 'Limited',
        'registration_ip' => $request->ip(),
        'user_agent' => $request->header('User-Agent'),
        'terms_accepted_at' => now(),
    ]);

    $user->assignRole('Partner');
    $emailVerification->sendCode($user, $request, ['source' => 'register_partner']);

    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'userType' => $user->role, 
        'verification_required' => true,
    ], 201);
}

public function verifyEmail(Request $request, EmailVerificationService $emailVerification)
{
    $validated = $request->validate([
        'email' => 'required|email',
        'code' => 'required|string',
    ]);

    $user = User::where('email', $validated['email'])->first();
    if (!$user) {
        return response()->json(['message' => 'Invalid code, please try again'], 422);
    }

    if ($user->email_verified_at) {
        $emailVerification->recordVerifyAttempt($user, 'already_verified', $request);
        return response()->json(['message' => 'Email already verified'], 200);
    }

    $token = \App\Models\EmailVerificationToken::where('user_id', $user->id)
        ->whereNull('used_at')
        ->orderByDesc('created_at')
        ->first();
    if (!$token) {
        $emailVerification->recordVerifyAttempt($user, 'missing', $request);
        return response()->json(['message' => 'Invalid code, please try again'], 422);
    }

    $result = $emailVerification->verifyToken($token, $validated['code']);
    if (!$result['ok']) {
        $emailVerification->recordVerifyAttempt($user, $result['reason'], $request);
        return response()->json(['message' => 'Invalid code, please try again'], 422);
    }

    $user->email_verified_at = now();
    $user->email_verification_code = null;
    $user->email_verification_expires_at = null;
    if (strtolower((string) $user->role) === 'partner') {
        $user->status = 'pending_agreement';
    } else {
        $user->status = 'active';
    }
    $user->save();

    $emailVerification->recordVerifyAttempt($user, 'verified', $request);

    return response()->json(['message' => 'Email verified successfully']);
}

public function resendVerificationCode(Request $request, EmailVerificationService $emailVerification)
{
    $validated = $request->validate([
        'email' => 'required|email',
    ]);

    $user = User::where('email', $validated['email'])->first();
    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    if ($user->email_verified_at) {
        return response()->json(['message' => 'Email already verified'], 200);
    }

    $emailVerification->sendCode($user, $request, ['source' => 'resend']);

    return response()->json([
        'message' => 'A confirmation code has been sent to your email. Please check your inbox.',
    ]);
}



// app/Http/Controllers/UserController.php

/**
 * Impersonate a specific user.
 */
public function impersonate(User $user)
{
    // Security: Only Admins can impersonate
    if (Auth::user()->role !== 'Admin') {
        return response()->json(['message' => 'Insufficient clearance for identity assumption.'], 403);
    }

    // Log impersonation
    $this->logActivity(
        'user_impersonated',
        "Admin {auth()->user()->name} impersonated user {$user->name}",
        ['admin_id' => Auth::id(), 'target_user_id' => $user->id],
        true
    );

    // Create a new token for the target user
    $token = $user->createToken('impersonation_token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'message' => "Logged in as {$user->name}",
        'user' => [
            'id' => $user->id,
            'role' => $user->role,
            'name' => $user->name,
            'email' => $user->email,
        ]
    ]);
}

/**
 * Get staff users for task assignment
 */
public function getStaff()
{
    try {
        $staff = User::whereIn('role', ['Admin', 'Employee', 'Partner'])
            ->where('status', 'Active')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email', 'role']);
        
        return response()->json($staff);
    } catch (\Exception $e) {
        \Log::error('Error fetching staff: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to fetch staff'], 500);
    }
}

// Add this method to UserController.php
public function getEmployeesForTasks()
{
    try {
        // Get all active non-customer users for task assignment
        $employees = User::where('status', 'Active')
            ->whereIn('role', ['Admin', 'Employee', 'Partner'])
            ->select('id', 'name', 'email', 'role')
            ->orderBy('name', 'asc')
            ->get();
            
        return response()->json($employees);
    } catch (\Exception $e) {
        \Log::error('Error fetching employees for tasks: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to fetch users'], 500);
    }
}
/**
 * Get the currently authenticated user.
 */
public function currentUser(Request $request)
{
    $user = $request->user();
    
    // Eager load permissions if needed by frontend
    $user->load(['permissions', 'partnerProfile']);
    
    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,
        'status' => $user->status,
        'access_level' => $user->access_level,
        'partner_token' => $user->partner_token,
        'permissions' => $user->getPermissionNames(),
        'partner_profile' => $user->partnerProfile,
    ]);
}
}
