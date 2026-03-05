<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ActivityLogController extends Controller
{
    /**
     * Get all activity logs with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $query = ActivityLog::with(['user:id,name,email,role'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('severity')) {
                $query->whereIn('severity', explode(',', $request->input('severity')));
            }

            if ($request->has('type')) {
                $query->whereIn('type', explode(',', $request->input('type')));
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->input('start_date'),
                    $request->input('end_date')
                ]);
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('action', 'like', "%{$search}%")
                      ->orWhere('entity_type', 'like', "%{$search}%")
                      ->orWhere('entity_name', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->input('per_page', 50);
            $logs = $query->paginate($perPage);

            return response()->json([
                'logs' => $logs->items(),
                'pagination' => [
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching activity logs: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch activity logs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $stats = [
                'total_logs' => ActivityLog::count(),
                'today_logs' => ActivityLog::whereDate('created_at', today())->count(),
                'unique_users' => ActivityLog::distinct('user_id')->count('user_id'),
                'by_severity' => ActivityLog::select('severity', \DB::raw('count(*) as count'))
                    ->groupBy('severity')
                    ->get()->pluck('count', 'severity'),
                'by_type' => ActivityLog::select('type', \DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->get()->pluck('count', 'type'),
                'recent_activity' => ActivityLog::with(['user:id,name'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            \Log::error('Error fetching activity stats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch statistics'], 500);
        }
    }

    /**
     * Get user-specific activity
     */
    public function userActivity(Request $request, $userId): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Only admin can view other users' activity
            if ($user->role !== 'Admin' && $user->id != $userId) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $logs = ActivityLog::with(['user:id,name,email,role'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate(50);

            return response()->json($logs);

        } catch (\Exception $e) {
            \Log::error('Error fetching user activity: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch user activity'], 500);
        }
    }

    /**
     * Get current user's activity
     */
    public function myActivity(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $logs = ActivityLog::with(['user:id,name,email,role'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(50);

            return response()->json($logs);

        } catch (\Exception $e) {
            \Log::error('Error fetching my activity: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch your activity'], 500);
        }
    }

    /**
     * Clear old logs (Admin only)
     */
    public function clearOldLogs(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->role !== 'Admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $days = $request->input('days', 30);
            $cutoffDate = now()->subDays($days);

            $deleted = ActivityLog::where('created_at', '<', $cutoffDate)->delete();

            // Log this action
            $this->logActivity(
                $user->id,
                'system',
                'LOGS_CLEARED',
                "Cleared {$deleted} old activity logs older than {$days} days",
                null,
                'info'
            );

            return response()->json([
                'message' => "Successfully cleared {$deleted} old logs",
                'deleted_count' => $deleted
            ]);

        } catch (\Exception $e) {
            \Log::error('Error clearing old logs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to clear old logs'], 500);
        }
    }

    /**
     * Helper method to log activity (to be used in other controllers)
     */
    public static function logActivity(
        $userId,
        $entityType,
        $action,
        $description,
        $entityId = null,
        $severity = 'info',
        $ipAddress = null,
        $userAgent = null
    ): void {
        try {
            ActivityLog::create([
                'user_id' => $userId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'description' => $description,
                'severity' => $severity,
                'ip_address' => $ipAddress ?? request()->ip(),
                'user_agent' => $userAgent ?? request()->header('User-Agent'),
                'metadata' => json_encode([
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'timestamp' => now()->toISOString(),
                ])
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to log activity: ' . $e->getMessage());
        }
    }
}