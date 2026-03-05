<?php
// app/Services/SystemLogService.php

namespace App\Services;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class SystemLogService
{
    /**
     * Log any system event
     */
    public static function log(
        string $eventType,
        string $entityType,
        ?int $entityId = null,
        ?array $oldData = null,
        ?array $newData = null,
        string $description = '',
        ?Request $request = null
    ): SystemLog {
        // Calculate changes if both old and new data provided
        $changes = null;
        if ($oldData && $newData) {
            $changes = self::calculateChanges($oldData, $newData);
        }
        
        // Get user info
        $user = Auth::user();
        $userId = $user ? $user->id : null;
        
        // Get request info if available
        $ipAddress = null;
        $userAgent = null;
        
        if ($request) {
            $ipAddress = $request->ip();
            $userAgent = $request->header('User-Agent');
        }
        
        // Create the log entry
        $log = SystemLog::create([
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'old_data' => $oldData,
            'new_data' => $newData,
            'changes' => $changes,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
        
        return $log;
    }
    
    /**
     * Calculate differences between old and new data
     */
    private static function calculateChanges(array $oldData, array $newData): array
    {
        $changes = [];
        
        // Check for changed values
        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }
        
        // Check for removed values
        foreach ($oldData as $key => $oldValue) {
            if (!array_key_exists($key, $newData)) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => null
                ];
            }
        }
        
        return $changes;
    }
    
    /**
     * Log task updates
     */
    public static function logTaskUpdate(
        Model $task,
        array $oldData,
        array $newData,
        ?Request $request = null
    ): SystemLog {
        $description = "Task '{$task->title}' updated by " . (Auth::user() ? Auth::user()->name : 'System');
        
        return self::log(
            'task_updated',
            'Task',
            $task->id,
            $oldData,
            $newData,
            $description,
            $request
        );
    }
    
    /**
     * Log yacht updates
     */
    public static function logYachtUpdate(
        Model $yacht,
        array $oldData,
        array $newData,
        ?Request $request = null
    ): SystemLog {
        $description = "Yacht '{$yacht->boat_name}' updated by " . (Auth::user() ? Auth::user()->name : 'System');
        
        return self::log(
            'yacht_updated',
            'Yacht',
            $yacht->id,
            $oldData,
            $newData,
            $description,
            $request
        );
    }
    
    /**
     * Log bid creation
     */
    public static function logBidCreation(
        Model $bid,
        ?Request $request = null
    ): SystemLog {
        $description = "Bid #{$bid->id} for €{$bid->amount} created by " . (Auth::user() ? Auth::user()->name : 'System');
        
        return self::log(
            'bid_created',
            'Bid',
            $bid->id,
            null,
            $bid->toArray(),
            $description,
            $request
        );
    }
    
    /**
     * Log bid updates
     */
    public static function logBidUpdate(
        Model $bid,
        array $oldData,
        array $newData,
        ?Request $request = null
    ): SystemLog {
        $description = "Bid #{$bid->id} updated to status: '{$newData['status']}' by " . (Auth::user() ? Auth::user()->name : 'System');
        
        return self::log(
            'bid_updated',
            'Bid',
            $bid->id,
            $oldData,
            $newData,
            $description,
            $request
        );
    }
    
    /**
     * Log user login
     */
    public static function logUserLogin(
        Model $user,
        ?Request $request = null
    ): SystemLog {
        $description = "User '{$user->name}' logged in";
        
        return self::log(
            'user_login',
            'User',
            $user->id,
            null,
            ['login_time' => now()->toDateTimeString()],
            $description,
            $request
        );
    }
    
    /**
     * Log user logout
     */
    public static function logUserLogout(
        Model $user,
        ?Request $request = null
    ): SystemLog {
        $description = "User '{$user->name}' logged out";
        
        return self::log(
            'user_logout',
            'User',
            $user->id,
            null,
            ['logout_time' => now()->toDateTimeString()],
            $description,
            $request
        );
    }

    /**
     * Log authentication failure
     */
    public static function logAuthFailure(
        string $email,
        ?Model $user = null,
        ?Request $request = null,
        ?array $context = null
    ): SystemLog {
        $description = "Failed login attempt for {$email}";

        return self::log(
            'auth_login_failed',
            'User',
            $user?->id,
            null,
            $context,
            $description,
            $request
        );
    }

    /**
     * Log authentication soft lock
     */
    public static function logAuthSoftLock(
        string $email,
        ?Model $user = null,
        ?Request $request = null,
        ?array $context = null
    ): SystemLog {
        $description = "Soft lock triggered for {$email}";

        return self::log(
            'auth_login_locked',
            'User',
            $user?->id,
            null,
            $context,
            $description,
            $request
        );
    }

    /**
     * Log OTP events
     */
    public static function logOtpEvent(
        string $event,
        Model $user,
        ?Request $request = null,
        ?array $context = null
    ): SystemLog {
        $description = "OTP {$event} for {$user->email}";

        return self::log(
            "auth_otp_{$event}",
            'User',
            $user->id,
            null,
            $context,
            $description,
            $request
        );
    }

    /**
     * Log session events
     */
    public static function logSessionEvent(
        string $event,
        Model $user,
        ?Request $request = null,
        ?array $context = null
    ): SystemLog {
        $description = "Session {$event} for {$user->email}";

        return self::log(
            "session_{$event}",
            'User',
            $user->id,
            null,
            $context,
            $description,
            $request
        );
    }

    /**
     * Log device events
     */
    public static function logDeviceEvent(
        string $event,
        Model $user,
        ?Request $request = null,
        ?array $context = null
    ): SystemLog {
        $description = "Device {$event} for {$user->email}";

        return self::log(
            "device_{$event}",
            'User',
            $user->id,
            null,
            $context,
            $description,
            $request
        );
    }
    
    /**
     * Log task creation
     */
    public static function logTaskCreation(
        Model $task,
        ?Request $request = null
    ): SystemLog {
        $description = "Task '{$task->title}' created by " . (Auth::user() ? Auth::user()->name : 'System');
        
        return self::log(
            'task_created',
            'Task',
            $task->id,
            null,
            $task->toArray(),
            $description,
            $request
        );
    }
    
    /**
     * Log yacht creation
     */
    public static function logYachtCreation(
        Model $yacht,
        ?Request $request = null
    ): SystemLog {
        $description = "Yacht '{$yacht->boat_name}' created by " . (Auth::user() ? Auth::user()->name : 'System');
        
        return self::log(
            'yacht_created',
            'Yacht',
            $yacht->id,
            null,
            $yacht->toArray(),
            $description,
            $request
        );
    }
    
    /**
     * Log task deletion
     */
    public static function logTaskDeletion(
        array $taskData,
        ?Request $request = null
    ): SystemLog {
        $description = "Task '{$taskData['title']}' deleted by " . (Auth::user() ? Auth::user()->name : 'System');
        
        return self::log(
            'task_deleted',
            'Task',
            $taskData['id'] ?? null,
            $taskData,
            null,
            $description,
            $request
        );
    }
    
    /**
     * Log bid acceptance
     */
    public static function logBidAccepted(
        Model $bid,
        ?Request $request = null
    ): SystemLog {
        $description = "Bid #{$bid->id} accepted by " . (Auth::user() ? Auth::user()->name : 'System');
        
        return self::log(
            'bid_accepted',
            'Bid',
            $bid->id,
            null,
            ['status' => 'won', 'finalized_at' => now()->toDateTimeString()],
            $description,
            $request
        );
    }
    
    /**
     * Log bid decline
     */
    public static function logBidDeclined(
        Model $bid,
        ?Request $request = null
    ): SystemLog {
        $description = "Bid #{$bid->id} declined by " . (Auth::user() ? Auth::user()->name : 'System');
        
        return self::log(
            'bid_declined',
            'Bid',
            $bid->id,
            null,
            ['status' => 'cancelled', 'finalized_at' => now()->toDateTimeString()],
            $description,
            $request
        );
    }

    /**
 * Log bid deletion
 */
public static function logBidDeletion(
    Model $bid,
    ?Request $request = null
): SystemLog {
    $description = "Bid #{$bid->id} for €{$bid->amount} deleted by " . (Auth::user() ? Auth::user()->name : 'System');
    
    return self::log(
        'bid_deleted',
        'Bid',
        $bid->id,
        $bid->toArray(),   // old data
        null,              // no new data
        $description,
        $request
    );
}
}
