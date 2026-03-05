<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskAttachment;
use App\Models\TaskActivityLog;
use App\Events\TaskCreated;
use App\Mail\TaskReminderMail;
use App\Services\NotificationDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    /**
     * Get tasks based on user role
     */
    // Taskcontroller
public function index(Request $request)
{
    $user = $request->user();

    if ($user->role === 'Admin') {
        $tasks = Task::with(['assignedTo', 'creator', 'yacht'])->latest()->get();
    } elseif ($user->role === 'Partner') {
        $employeeIds = User::where('partner_id', $user->id)->pluck('id');
        $tasks = Task::with(['assignedTo', 'creator', 'yacht'])
            ->where(function ($q) use ($user, $employeeIds) {
                $q->where('created_by', $user->id)
                  ->orWhereIn('assigned_to', $employeeIds);
            })
            ->latest()
            ->get();
    } else {
        $tasks = Task::with(['assignedTo', 'creator', 'yacht'])
            ->forUser($user->id)
            ->latest()
            ->get();
    }

    return response()->json($tasks);
}

    /**
     * Create a new task
     */
// In TaskController.php, update the store method validation:

public function store(Request $request)
{
    try {
        $user = $request->user();
        \Illuminate\Support\Facades\Log::info('Task Store Payload: ', $request->all());

        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'required|in:Low,Medium,High,Urgent,Critical',
            'status'      => 'nullable|in:New,Pending,To Do,In Progress,Done',
            'due_date'    => 'required|date',
            'reminder_at' => 'nullable|date',
            'type'        => 'required|in:personal,assigned',
            'assigned_to' => 'required_if:type,assigned|nullable|integer|exists:users,id',
            'yacht_id'    => 'nullable|integer|exists:yachts,id',
            'column_id'   => 'nullable|integer|exists:columns,id',
            'position'    => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        if (empty($data['status'])) {
            $data['status'] = 'New';
        }
        $data['created_by'] = $user->id;

        if ($data['type'] === 'personal') {
            $data['user_id'] = $user->id;
            $data['assigned_to'] = $user->id;
            $data['assignment_status'] = 'accepted';
        } else {
            $data['assignment_status'] = 'pending';
            $data['user_id'] = null;
        }

        // Safely cast assigned_to if present
        if (!empty($data['assigned_to'])) {
            $data['assigned_to'] = (int)$data['assigned_to'];
        } else {
            $data['assigned_to'] = null;
        }

        // Safely handle yacht_id – only set if present
        if (array_key_exists('yacht_id', $data) && !empty($data['yacht_id'])) {
            $data['yacht_id'] = (int)$data['yacht_id'];
        } else {
            $data['yacht_id'] = null; // ensure the key exists with null value
        }

        $task = Task::create($data);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => 'created',
            'description' => 'Task created'
        ]);

        event(new TaskCreated($task, $user));

        return response()->json($task->load(['assignedTo', 'creator', 'yacht']), 201);
    } catch (\Exception $e) {
        Log::error('Task store error: ' . $e->getMessage());
        return response()->json(['error' => 'Internal server error: ' . $e->getMessage()], 500);
    }
}
    /**
     * Get a specific task
     */
    public function show($id)
    {
        try {
            $task = Task::with(['assignedTo', 'yacht', 'creator'])->find($id);
            
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            $user = request()->user();
            
            // Check permissions
            if ($user->role !== 'Admin' && 
                $task->assigned_to !== $user->id && 
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            if ($task->assigned_to === $user->id && $task->status === 'New') {
                $task->update(['status' => 'Pending']);
                $task = $task->fresh(['assignedTo', 'yacht', 'creator']);
            }

            return response()->json($task);
            
        } catch (\Exception $e) {
            Log::error('Error fetching task: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update a task
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $task = Task::find($id);
            
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            // Check permissions
            if ($user->role !== 'Admin' && 
                $task->assigned_to !== $user->id && 
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'priority' => 'sometimes|in:Low,Medium,High,Urgent,Critical',
                'status' => 'sometimes|in:New,Pending,To Do,In Progress,Done',
                'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
                'yacht_id' => 'nullable|exists:yachts,id',
                'due_date' => 'sometimes|date',
                'reminder_at' => 'nullable|date',
                'column_id' => 'nullable|exists:columns,id',
                'position' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            if ($request->has('reminder_at')) {
                $request->merge(['reminder_sent_at' => null]);
            }

            // Employees can only update status and their personal tasks
            if ($user->role !== 'Admin' && $task->type === 'assigned') {
                $request->merge(['status' => $request->input('status', $task->status)]);
                // Only allow status update for assigned tasks
                $task->update(['status' => $request->status]);
            } else {
                $data = $request->all();
                
                if (isset($data['type'])) {
                    if ($data['type'] === 'personal') {
                        $data['user_id'] = $user->id;
                        $data['assigned_to'] = $user->id;
                        $data['assignment_status'] = 'accepted';
                    } else {
                        $data['user_id'] = null;
                        $newAssignee = $data['assigned_to'] ?? $task->assigned_to;
                        if ($task->type === 'personal' || $task->assigned_to != $newAssignee) {
                            $data['assignment_status'] = 'pending';
                            if (!isset($data['status'])) {
                                $data['status'] = 'New';
                            }
                        }
                    }
                }

                if (array_key_exists('assigned_to', $data) && empty($data['assigned_to']) && (!isset($data['type']) || $data['type'] !== 'personal')) {
                    $data['assigned_to'] = null;
                }

                $task->update($data);
            }

            TaskActivityLog::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'action' => 'updated',
                'description' => 'Task details updated'
            ]);

            return response()->json($task->load(['assignedTo', 'yacht', 'creator']));
            
        } catch (\Exception $e) {
            Log::error('Error updating task: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Delete a task
     */
    public function destroy($id)
    {
        try {
            $user = request()->user();
            $task = Task::find($id);
            
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            // Check permissions
            if ($user->role !== 'Admin' && 
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            TaskActivityLog::where('task_id', $task->id)->delete();
            $task->delete();

            return response()->json(['message' => 'Task deleted successfully']);
            
        } catch (\Exception $e) {
            Log::error('Error deleting task: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = $request->user();
            $task = Task::find($id);
            
            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            // Check permissions
            if ($user->role !== 'Admin' && 
                $task->assigned_to !== $user->id && 
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:New,Pending,To Do,In Progress,Done'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $task->update(['status' => $request->status]);

            TaskActivityLog::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'action' => 'status_updated',
                'description' => "Status changed to {$request->status}"
            ]);

            return response()->json($task);
            
        } catch (\Exception $e) {
            Log::error('Error updating task status: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Reschedule a task due date (optional reminder update)
     */
    public function reschedule(Request $request, $id)
    {
        try {
            $user = $request->user();
            $task = Task::find($id);

            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            if ($user->role !== 'Admin' &&
                $task->assigned_to !== $user->id &&
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'due_date' => 'required|date',
                'reminder_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $task->due_date = $request->due_date;

            if ($request->has('reminder_at')) {
                $task->reminder_at = $request->input('reminder_at');
                $task->reminder_sent_at = null;
            }

            $task->save();

            return response()->json($task->fresh(['assignedTo', 'yacht', 'creator']));
        } catch (\Exception $e) {
            Log::error('Error rescheduling task: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Schedule or clear a reminder for a task
     */
    public function scheduleReminder(Request $request, $id)
    {
        try {
            $user = $request->user();
            $task = Task::find($id);

            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            if ($user->role !== 'Admin' &&
                $task->assigned_to !== $user->id &&
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'reminder_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $task->reminder_at = $request->input('reminder_at');
            $task->reminder_sent_at = null;
            $task->save();

            return response()->json($task->fresh(['assignedTo', 'yacht', 'creator']));
        } catch (\Exception $e) {
            Log::error('Error scheduling task reminder: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Send an immediate reminder for a task
     */
    public function remind(Request $request, $id)
    {
        try {
            $user = $request->user();
            $task = Task::with(['assignedTo', 'creator', 'user'])->find($id);

            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            if ($user->role !== 'Admin' &&
                $task->assigned_to !== $user->id &&
                $task->user_id !== $user->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'realtime' => 'sometimes|boolean',
                'email' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $recipient = $task->assignedTo ?: ($task->user ?: $task->creator);
            if (!$recipient) {
                return response()->json(['error' => 'No recipient found for this task'], 422);
            }

            $allowRealtime = $request->boolean('realtime', true);
            $allowEmail = $request->boolean('email', true);
            $email = $allowEmail ? new TaskReminderMail($task, $user) : null;

            $service = new NotificationDispatchService();
            $notification = $service->notifyUser(
                $recipient,
                'info',
                'Task reminder',
                "Reminder: {$task->title}",
                [
                    'task_id' => $task->id,
                    'task_type' => $task->type,
                    'assignment_status' => $task->assignment_status,
                    'related_type' => $task->yacht_id ? 'yacht' : null,
                    'related_id' => $task->yacht_id,
                ],
                $email,
                $allowRealtime,
                $allowEmail
            );

            return response()->json([
                'message' => 'Reminder sent',
                'notification' => $notification,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending task reminder: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
     * Reorder tasks (drag and drop)
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:tasks,id',
            'tasks.*.position' => 'required|integer',
            'tasks.*.column_id' => 'required|exists:columns,id'
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            foreach ($request->tasks as $taskData) {
                Task::where('id', $taskData['id'])->update([
                    'position' => $taskData['position'],
                    'column_id' => $taskData['column_id']
                ]);
            }
        });

        // Optional: log movement for each task, using system user or assuming request user
        // We'll skip individual task logs here to avoid spamming the DB on drag,
        // unless it's strictly required. Given drag & drop is frequent, 
        // we'll keep the log clean.

        return response()->json(['message' => 'Tasks reordered']);
    }

    /**
     * Get current user's tasks
     */
    public function myTasks(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $tasks = Task::with(['assignedTo:id,name,email', 'yacht:id,name', 'creator:id,name'])
                ->where(function($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                          ->orWhere('user_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($tasks);
            
        } catch (\Exception $e) {
            Log::error('Error fetching user tasks: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get tasks by user (for admin)
     */
    public function getUserTasks($userId)
    {
        try {
            $user = request()->user();
            
            if (!$user || $user->role !== 'Admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $tasks = Task::with(['assignedTo', 'yacht', 'creator'])
                ->where('assigned_to', $userId)
                ->orWhere('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($tasks);
            
        } catch (\Exception $e) {
            Log::error('Error fetching user tasks: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get tasks for calendar view
     */
    public function calendarTasks(Request $request)
    {
        try {
            $user = $request->user();
            $start = $request->input('start');
            $end = $request->input('end');
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $query = Task::with(['assignedTo:id,name', 'yacht:id,name']);
            
            if ($user->role !== 'Admin') {
                $query->where(function($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere('user_id', $user->id);
                });
            }

            if ($start && $end) {
                $query->whereBetween('due_date', [$start, $end]);
            }

            $tasks = $query->get()->map(function($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'start' => $task->due_date,
                    'end' => $task->due_date ? (new \DateTime($task->due_date))->modify('+1 day')->format('Y-m-d') : null,
                    'priority' => $task->priority,
                    'status' => $task->status,
                    'type' => $task->type,
                    'assigned_to' => $task->assignedTo ? $task->assignedTo->name : null,
                    'yacht' => $task->yacht ? $task->yacht->name : null,
                    'color' => $this->getPriorityColor($task->priority),
                ];
            });

            return response()->json($tasks);
            
        } catch (\Exception $e) {
            Log::error('Error fetching calendar tasks: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function getPriorityColor($priority)
    {
        switch ($priority) {
            case 'Critical': return '#dc2626';
            case 'Urgent': return '#ea580c';
            case 'High': return '#d97706';
            case 'Medium': return '#3b82f6';
            case 'Low': return '#6b7280';
            default: return '#6b7280';
        }
    }
    public function scopeForUser($query, $userId)
{
    return $query->where(function ($q) use ($userId) {
        $q->where('assigned_to', $userId)
          ->orWhere('user_id', $userId)
          ->orWhere('created_by', $userId);
    });
}

public function getPartnerEmployees(Request $request)
{
    $user = $request->user();

    if ($user->role === 'Partner') {
        $employees = User::where('partner_id', $user->id)
            ->where('role', 'Employee')   // <-- filter only employees
            ->where('status', 'Active')
            ->select('id', 'name', 'email', 'role')
            ->get();
    } elseif ($user->role === 'Employee' && $user->partner_id) {
        $employees = User::where('partner_id', $user->partner_id)
            ->where('role', 'Employee')
            ->where('id', '!=', $user->id)
            ->where('status', 'Active')
            ->select('id', 'name', 'email', 'role')
            ->get();
    } else {
        return response()->json([]);
    }

    return response()->json($employees);
}

    // ==========================================
    // Attachments & Activity Logs
    // ==========================================

    public function getActivities($id)
    {
        $task = Task::findOrFail($id);
        $user = request()->user();

        if ($user->role !== 'Admin' && $task->assigned_to !== $user->id && $task->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $activities = TaskActivityLog::with('user:id,name,email')
            ->where('task_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $attachments = TaskAttachment::with('user:id,name')
            ->where('task_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'activities' => $activities,
            'attachments' => $attachments
        ]);
    }

    public function addComment(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        $task = Task::findOrFail($id);
        $user = $request->user();

        if ($user->role !== 'Admin' && $task->assigned_to !== $user->id && $task->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $log = TaskActivityLog::create([
            'task_id' => $id,
            'user_id' => $user->id,
            'action' => 'commented',
            'description' => $request->content
        ]);

        return response()->json($log->load('user:id,name,email'), 201);
    }

    public function uploadAttachment(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|max:10240' // max 10MB
        ]);

        $task = Task::findOrFail($id);
        $user = $request->user();

        if ($user->role !== 'Admin' && $task->assigned_to !== $user->id && $task->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $file = $request->file('file');
        $path = $file->store('task_attachments', 'public');

        $attachment = TaskAttachment::create([
            'task_id' => $id,
            'user_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        TaskActivityLog::create([
            'task_id' => $id,
            'user_id' => $user->id,
            'action' => 'attachment_added',
            'description' => "Uploaded file: " . $file->getClientOriginalName()
        ]);

        return response()->json($attachment->load('user:id,name'), 201);
    }

    public function deleteAttachment($taskId, $attachmentId)
    {
        $user = request()->user();
        $task = Task::findOrFail($taskId);
        $attachment = TaskAttachment::where('task_id', $taskId)->findOrFail($attachmentId);

        if ($user->role !== 'Admin' && $attachment->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        TaskActivityLog::create([
            'task_id' => $taskId,
            'user_id' => $user->id,
            'action' => 'attachment_removed',
            'description' => "Removed file: " . $attachment->file_name
        ]);

        return response()->json(['message' => 'Attachment deleted successfully']);
    }

}
