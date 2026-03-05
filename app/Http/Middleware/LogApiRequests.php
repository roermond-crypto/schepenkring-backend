<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;
use App\Models\UserDevice;
use App\Services\DeviceInfoService;

class LogApiRequests
{
    public function handle(Request $request, Closure $next)
    {
        // Skip logging for certain endpoints
        $skipPaths = ['/api/analytics/track', '/api/notifications', '/api/harbor/widget/events'];
        
        if (in_array($request->path(), $skipPaths)) {
            return $next($request);
        }

        $response = $next($request);

        try {
            $user = $request->user();
            $action = $this->getActionFromRequest($request);
            
            ActivityLog::log(
                'api_call',
                $action,
                $this->getDescription($request, $response),
                $user ? $user->id : null,
                [
                    'method' => $request->method(),
                    'endpoint' => $request->path(),
                    'status_code' => $response->getStatusCode(),
                    'parameters' => $this->sanitizeParameters($request->all()),
                    'request_id' => $request->attributes->get('request_id'),
                ]
            );

            // Send notification for important actions
            $this->sendNotificationIfImportant($request, $action, $user);

            $this->touchSessionDevice($request, $user);
        } catch (\Exception $e) {
            Log::error('Failed to log API request: ' . $e->getMessage());
        }

        return $response;
    }

    private function touchSessionDevice(Request $request, $user): void
    {
        if (!$user) {
            return;
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return;
        }

        $deviceInfo = app(DeviceInfoService::class);
        $location = $deviceInfo->resolveLocation($request);

        $token->last_seen_at = now();
        $token->ip_address = $request->ip();
        $token->ip_country = $location['country'] ?? $token->ip_country;
        $token->ip_city = $location['city'] ?? $token->ip_city;
        $token->ip_asn = $location['asn'] ?? $token->ip_asn;
        $token->save();

        if (!empty($token->device_id)) {
            UserDevice::where('user_id', $user->id)
                ->where('device_id', $token->device_id)
                ->update([
                    'last_seen_at' => now(),
                    'last_ip_address' => $request->ip(),
                    'last_ip_country' => $location['country'] ?? null,
                    'last_ip_city' => $location['city'] ?? null,
                    'last_ip_asn' => $location['asn'] ?? null,
                ]);
        }
    }

    private function getActionFromRequest(Request $request): string
    {
        $path = $request->path();
        
        // Map paths to actions
        $actions = [
            'login' => 'login',
            'register' => 'registration',
            'yachts' => $request->isMethod('POST') ? 'yacht_created' : 'yacht_viewed',
            'bids/place' => 'bid_placed',
            'tasks' => 'task_action',
            'book' => 'booking_created',
            'profile/update' => 'profile_updated',
            'blogs' => $request->isMethod('POST') ? 'blog_created' : 'blog_viewed',
            'bids/accept' => 'bid_accepted',
            'bids/decline' => 'bid_declined',
            'users/impersonate' => 'user_impersonated',
            'users/toggle-status' => 'user_status_toggled'
        ];

        foreach ($actions as $key => $action) {
            if (str_contains($path, $key)) {
                return $action;
            }
        }

        return 'api_request';
    }

    private function getDescription(Request $request, $response): string
    {
        $user = $request->user();
        $userName = $user ? $user->name : 'Guest';
        $method = $request->method();
        $path = $request->path();
        $status = $response->getStatusCode();

        return "{$userName} {$method} {$path} - Status: {$status}";
    }

    private function sanitizeParameters(array $params): array
    {
        // Remove sensitive data
        $sensitiveKeys = ['password', 'token', 'api_key', 'secret', 'credit_card'];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($params[$key])) {
                $params[$key] = '***REDACTED***';
            }
        }

        return $params;
    }

    private function sendNotificationIfImportant(Request $request, string $action, $user): void
    {
        $importantActions = [
            'login' => true,
            'registration' => true,
            'yacht_created' => true,
            'bid_placed' => true,
            'bid_accepted' => true,
            'bid_declined' => true,
            'booking_created' => true,
            'user_impersonated' => true,
            'user_status_toggled' => true
        ];

        if (isset($importantActions[$action])) {
            $userName = $user ? $user->name : 'Unknown User';
            
            \App\Models\Notification::createAndSend(
                'info',
                ucfirst(str_replace('_', ' ', $action)),
                "{$userName} performed: {$action}",
                $this->getAdminIds(),
                [
                    'action' => $action,
                    'user_id' => $user ? $user->id : null,
                    'user_name' => $userName,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toDateTimeString()
                ]
            );
        }
    }

    private function getAdminIds(): array
    {
        return \App\Models\User::where('role', 'Admin')
            ->where('status', 'Active')
            ->pluck('id')
            ->toArray();
    }
}
