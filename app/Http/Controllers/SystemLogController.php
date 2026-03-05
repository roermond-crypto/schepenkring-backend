<?php
// app/Http/Controllers/SystemLogController.php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SystemLogController extends Controller
{
    /**
     * Get all system logs with filters (admin endpoint)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SystemLog::with(['user:id,name,email,role'])
                ->orderBy('created_at', 'desc');
            
            // Apply filters
            if ($request->has('event_type')) {
                $eventTypes = is_array($request->event_type) ? $request->event_type : [$request->event_type];
                $query->whereIn('event_type', $eventTypes);
            }
            
            if ($request->has('entity_type')) {
                $entityTypes = is_array($request->entity_type) ? $request->entity_type : [$request->entity_type];
                $query->whereIn('entity_type', $entityTypes);
            }
            
            if ($request->has('entity_id')) {
                $query->where('entity_id', $request->entity_id);
            }
            
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhere('event_type', 'like', "%{$search}%")
                      ->orWhere('entity_type', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }
            
            // Date range filter
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 50);
            $logs = $query->paginate($perPage);
            
            return response()->json([
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching system logs: ' . $e->getMessage());
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get specific log details (admin endpoint)
     */
    public function show($id): JsonResponse
    {
        try {
            $log = SystemLog::with(['user:id,name,email,role'])->find($id);
            
            if (!$log) {
                return response()->json(['error' => 'Log not found'], 404);
            }
            
            return response()->json($log);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching log: ' . $e->getMessage());
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get activity summary (admin endpoint)
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            // Get total counts
            $totalLogs = SystemLog::count();
            
            // Get counts by event type
            $eventTypes = SystemLog::select('event_type')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('event_type')
                ->orderBy('count', 'desc')
                ->get();
            
            // Get counts by entity type
            $entityTypes = SystemLog::select('entity_type')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('entity_type')
                ->orderBy('count', 'desc')
                ->get();
            
            // Get recent activity (last 10 logs)
            $recentActivity = SystemLog::with(['user:id,name,email'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            // Get activity by date (last 30 days)
            $startDate = now()->subDays(30);
            $dailyActivity = SystemLog::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();
            
            return response()->json([
                'summary' => [
                    'total_logs' => $totalLogs,
                    'event_types' => $eventTypes,
                    'entity_types' => $entityTypes,
                ],
                'recent_activity' => $recentActivity,
                'daily_activity' => $dailyActivity,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error generating summary: ' . $e->getMessage());
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Export logs (CSV) - admin endpoint
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $query = SystemLog::with(['user:id,name,email']);
            
            // Apply filters same as index
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            
            if ($request->has('event_type')) {
                $eventTypes = is_array($request->event_type) ? $request->event_type : [$request->event_type];
                $query->whereIn('event_type', $eventTypes);
            }
            
            if ($request->has('entity_type')) {
                $entityTypes = is_array($request->entity_type) ? $request->entity_type : [$request->entity_type];
                $query->whereIn('entity_type', $entityTypes);
            }
            
            $logs = $query->orderBy('created_at', 'desc')->get();
            
            // Generate CSV content
            $csvData = "ID,Date,Time,Event Type,Entity Type,Entity ID,User,Description,IP Address,User Agent\n";
            
            foreach ($logs as $log) {
                $date = \Carbon\Carbon::parse($log->created_at)->format('Y-m-d');
                $time = \Carbon\Carbon::parse($log->created_at)->format('H:i:s');
                $userName = $log->user ? $log->user->name : 'System';
                $userEmail = $log->user ? $log->user->email : 'N/A';
                
                $csvData .= implode(',', [
                    $log->id,
                    $date,
                    $time,
                    '"' . $log->event_type . '"',
                    '"' . $log->entity_type . '"',
                    $log->entity_id ?? 'N/A',
                    '"' . $userName . ' (' . $userEmail . ')"',
                    '"' . str_replace('"', '""', $log->description) . '"',
                    $log->ip_address ?? 'N/A',
                    '"' . ($log->user_agent ? str_replace('"', '""', $log->user_agent) : 'N/A') . '"'
                ]) . "\n";
            }
            
            return response()->json([
                'csv_data' => $csvData,
                'filename' => 'system_logs_export_' . date('Y-m-d_H-i-s') . '.csv'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error exporting logs: ' . $e->getMessage());
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get system health check (public endpoint)
     */
    public function health(): JsonResponse
    {
        try {
            // Check database connection
            DB::connection()->getPdo();
            
            $totalLogs = SystemLog::count();
            $lastLog = SystemLog::latest()->first();
            
            return response()->json([
                'status' => 'healthy',
                'database' => 'connected',
                'total_logs' => $totalLogs,
                'last_log_at' => $lastLog ? $lastLog->created_at : null,
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'database' => 'disconnected',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }
    
    /**
     * Clear old logs (keep only last 90 days) - admin only
     */
    public function cleanup(): JsonResponse
    {
        if (!config('security.audit.allow_delete', false)) {
            return response()->json([
                'message' => 'Audit log deletion is disabled.',
            ], 403);
        }

        try {
            $cutoffDate = now()->subDays(90);
            $deletedCount = SystemLog::where('created_at', '<', $cutoffDate)->delete();
            
            return response()->json([
                'message' => 'Old logs cleaned up successfully',
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->toDateString(),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error cleaning up logs: ' . $e->getMessage());
            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
