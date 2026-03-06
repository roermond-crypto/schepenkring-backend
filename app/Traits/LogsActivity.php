<?php

namespace App\Traits;

use App\Models\ActivityLog;
use App\Models\Notification;

trait LogsActivity
{
    protected function logActivity($action, $description, $metadata = [], $notifyAdmins = false)
    {
        $logType = $this->getLogType();
        
        $log = ActivityLog::log(
            $logType,
            $action,
            $description,
            auth()->id(),
            $metadata
        );

        // Send notification to admins if required
        if ($notifyAdmins) {
            $user = auth()->user();
            
            Notification::createAndSend(
                'info',
                ucfirst(str_replace('_', ' ', $action)),
                "{$user->name} performed: {$action}",
                $this->getAdminIds(),
                array_merge($metadata, [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'action' => $action
                ])
            );
        }

        return $log;
    }

    protected function getLogType(): string
    {
        $className = class_basename($this);
        
        $types = [
            'YachtController' => 'yacht',
            'BidController' => 'bid',
            'UserController' => 'user',
            'TaskController' => 'task',
            'BookingController' => 'booking',
            'BlogController' => 'blog',
            'GeminiController' => 'ai',
            'ProfileController' => 'profile',
            'PagePermissionController' => 'permission'
        ];

        return $types[$className] ?? 'system';
    }

    protected function getAdminIds(): array
    {
        return \App\Models\User::where('role', 'Admin')
            ->where('status', 'Active')
            ->pluck('id')
            ->toArray();
    }
}