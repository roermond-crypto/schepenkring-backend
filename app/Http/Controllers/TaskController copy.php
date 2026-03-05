<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    /**
     * Get tasks based on user role
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Admin sees all tasks
            if ($user->role === 'Admin') {
                $tasks = Task::with(['assignedTo:id,name,email', 'yacht:id,name', 'creator:id,name'])
                    ->orderBy('created_at', 'desc')
                    ->get();
            } 
            // Employee sees only their assigned tasks and personal tasks
            else {
                $tasks = Task::with(['assignedTo:id,name,email', 'yacht:id,name', 'creator:id,name'])
                    ->where(function($query) use ($user) {
                        $query->where('assigned_to', $user->id)
                              ->orWhere('user_id', $user->id);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            return response()->json($tasks);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching tasks: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Create a new task
     */
// In TaskController.php, update the store method validation:

public function store(Request $request)
{
    try {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:Low,Medium,High,Urgent,Critical',
            'status' => 'required|in:To Do,In Progress,Done',
            'assigned_to' => 'required_if:type,assigned|integer|exists:users,id',
            'yacht_id' => 'nullable|integer|exists:yachts,id',
            'due_date' => 'required|date',
            'type' => 'required|in:assigned,personal',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['created_by'] = $user->id;
        
        // Convert empty strings to null
        $data['assigned_to'] = $data['assigned_to'] ? (int)$data['assigned_to'] : null;
        $data['yacht_id'] = $data['yacht_id'] ? (int)$data['yacht_id'] : null;
        
        // For personal tasks, set user_id to current user
        if ($data['type'] === 'personal') {
            $data['user_id'] = $user->id;
            $data['assigned_to'] = $user->id;
        }

        $task = Task::create($data);

        return response()->json($task->load(['assignedTo', 'yacht', 'creator']), 201);
        
    } catch (\Exception $e) {
        \Log::error('Error creating task: ' . $e->getMessage());
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

            return response()->json($task);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching task: ' . $e->getMessage());
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
                'status' => 'sometimes|in:To Do,In Progress,Done',
                'assigned_to' => 'sometimes|exists:users,id',
                'yacht_id' => 'nullable|exists:yachts,id',
                'due_date' => 'sometimes|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Employees can only update status and their personal tasks
            if ($user->role !== 'Admin' && $task->type === 'assigned') {
                $request->merge(['status' => $request->input('status', $task->status)]);
                // Only allow status update for assigned tasks
                $task->update(['status' => $request->status]);
            } else {
                $task->update($request->all());
            }

            return response()->json($task->load(['assignedTo', 'yacht', 'creator']));
            
        } catch (\Exception $e) {
            \Log::error('Error updating task: ' . $e->getMessage());
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

            $task->delete();

            return response()->json(['message' => 'Task deleted successfully']);
            
        } catch (\Exception $e) {
            \Log::error('Error deleting task: ' . $e->getMessage());
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
                'status' => 'required|in:To Do,In Progress,Done'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $task->update(['status' => $request->status]);

            return response()->json($task);
            
        } catch (\Exception $e) {
            \Log::error('Error updating task status: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
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
            \Log::error('Error fetching user tasks: ' . $e->getMessage());
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
            \Log::error('Error fetching user tasks: ' . $e->getMessage());
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
            \Log::error('Error fetching calendar tasks: ' . $e->getMessage());
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
}