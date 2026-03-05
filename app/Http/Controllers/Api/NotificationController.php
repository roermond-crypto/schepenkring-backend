<?php

namespace App\Http\Controllers\Api;

use App\Events\NotificationCountUpdated;
use App\Events\NotificationRead;
use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $query = UserNotification::where('user_id', $user->id)
                ->with('notification')
                ->orderBy('created_at', 'desc');

            if ($request->has('read')) {
                $query->where('read', filter_var($request->read, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('type')) {
                $type = $request->input('type');
                $query->whereHas('notification', function ($builder) use ($type) {
                    $builder->where('type', $type);
                });
            }

            $perPage = (int) $request->get('per_page', 20);
            $notifications = $query->paginate($perPage);

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
                    'unread_count' => $unreadCount,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching notifications: '.$e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch notifications',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function markAsRead(int $id, Request $request)
    {
        try {
            $user = $request->user();

            $userNotification = UserNotification::where('user_id', $user->id)
                ->where('id', $id)
                ->with('notification')
                ->first();

            if (! $userNotification) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Notification not found',
                ], 404);
            }

            $userNotification->markAsRead();
            $unreadCount = UserNotification::where('user_id', $user->id)
                ->where('read', false)
                ->count();

            $locationId = $userNotification->notification?->location_id;

            broadcast(new NotificationRead($userNotification, $unreadCount, $locationId));
            broadcast(new NotificationCountUpdated($user->id, $unreadCount, $locationId));

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error marking notification as read: '.$e->getMessage());
            return response()->json([
                'error' => 'Failed to mark notification as read',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->user();

            $updated = UserNotification::where('user_id', $user->id)
                ->where('read', false)
                ->update([
                    'read' => true,
                    'read_at' => now(),
                ]);

            $unreadCount = 0;
            broadcast(new NotificationCountUpdated($user->id, $unreadCount));

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'count' => $updated,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error marking all notifications as read: '.$e->getMessage());
            return response()->json([
                'error' => 'Failed to mark all notifications as read',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function unreadCount(Request $request)
    {
        try {
            $user = $request->user();

            $count = UserNotification::where('user_id', $user->id)
                ->where('read', false)
                ->count();

            return response()->json([
                'count' => $count,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching unread count: '.$e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch unread count',
                'message' => $e->getMessage(),
                'count' => 0,
            ], 500);
        }
    }
}
