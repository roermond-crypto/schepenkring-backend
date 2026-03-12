<?php

namespace App\Http\Controllers\Api;

use App\Events\NotificationCountUpdated;
use App\Events\NotificationRead;
use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        $query = $request->user()
            ->userNotifications()
            ->with('notification')
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        if ($request->filled('type')) {
            $query->whereHas('notification', function ($notificationQuery) use ($request) {
                $notificationQuery->where('type', $request->input('type'));
            });
        }

        return $query->paginate((int) $request->input('per_page', 25));
    }

    /**
     * Get unread count.
     */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => $request->user()->userNotifications()->unread()->count(),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, int $id)
    {
        $userNotification = $request->user()
            ->userNotifications()
            ->with('notification')
            ->findOrFail($id);

        if (! $userNotification->read) {
            $userNotification->markAsRead();

            $this->broadcastReadUpdate($request, $userNotification);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->userNotifications()->unread()->update([
            'read' => true,
            'read_at' => now(),
        ]);

        broadcast(new NotificationCountUpdated($request->user()->id, 0));

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    private function broadcastReadUpdate(Request $request, UserNotification $userNotification): void
    {
        $unreadCount = $request->user()->userNotifications()->unread()->count();
        $locationId = $userNotification->notification?->location_id;

        broadcast(new NotificationRead($userNotification, $unreadCount, $locationId));
        broadcast(new NotificationCountUpdated($request->user()->id, $unreadCount, $locationId));
    }
}
