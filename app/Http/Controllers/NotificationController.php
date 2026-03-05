<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationDispatchService;


class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // FIXED: Query UserNotification directly instead of $user->notifications()
            $query = UserNotification::where('user_id', $user->id)
                ->with('notification')
                ->orderBy('created_at', 'desc');

            // Filter by read status
            if ($request->has('read')) {
                $query->where('read', filter_var($request->read, FILTER_VALIDATE_BOOLEAN));
            }

            $perPage = $request->get('per_page', 20);
            $notifications = $query->paginate($perPage);

            // Get unread count
            $unreadCount = UserNotification::where('user_id', $user->id)
                ->where('read', false)
                ->count();

            return response()->json([
                'data' => $notifications->items(),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'unread_count' => $unreadCount
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch notifications',
                'message' => $e->getMessage()
            ], 500);
        }
    }
public function store(Request $request)
{
    try {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:info,warning,success,error,system',
            'data' => 'nullable|array',
        ]);

        $service = new NotificationDispatchService();
        $userNotification = $service->notifyUser(
            $user,
            $validated['type'] ?? 'info',
            $validated['title'],
            $validated['message'],
            $validated['data'] ?? [],
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification created',
            'notification' => $userNotification->load('notification'),
        ], 201);

    } catch (\Exception $e) {
        Log::error('Error creating notification: ' . $e->getMessage());
        return response()->json([
            'error' => 'Failed to create notification',
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function markAsRead($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $userNotification = UserNotification::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$userNotification) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Notification not found'
                ], 404);
            }

            $userNotification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to mark notification as read',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function markAllAsRead()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $updated = UserNotification::where('user_id', $user->id)
                ->where('read', false)
                ->update([
                    'read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'count' => $updated
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to mark all notifications as read',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getUnreadCount()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $count = UserNotification::where('user_id', $user->id)
                ->where('read', false)
                ->count();

            return response()->json([
                'count' => $count
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching unread count: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch unread count',
                'message' => $e->getMessage(),
                'count' => 0
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            $userNotification = UserNotification::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$userNotification) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Notification not found'
                ], 404);
            }

            $userNotification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting notification: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete notification',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAll()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $deleted = UserNotification::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'All notifications deleted',
                'count' => $deleted
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting all notifications: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete all notifications',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
