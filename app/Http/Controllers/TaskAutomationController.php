<?php

namespace App\Http\Controllers;

use App\Models\TaskAutomation;
use App\Models\TaskAutomationTemplate;
use App\Models\User;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TaskAutomationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['Admin', 'Partner'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = TaskAutomation::with('template');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('trigger_event')) {
            $query->where('trigger_event', $request->input('trigger_event'));
        }

        if ($request->filled('related_type')) {
            $query->where('related_type', $request->input('related_type'));
        }

        if ($request->filled('related_id')) {
            $query->where('related_id', $request->input('related_id'));
        }

        return response()->json($query->orderBy('due_at')->get());
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['Admin', 'Partner'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $automation = TaskAutomation::with('template')->find($id);
        if (!$automation) {
            return response()->json(['error' => 'Automation not found'], 404);
        }

        return response()->json($automation);
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['Admin', 'Partner'], true)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'template_id' => 'required|exists:task_automation_templates,id',
                'trigger_event' => 'nullable|string|max:255',
                'related_type' => 'nullable|string|max:255',
                'related_id' => 'nullable|integer',
                'assigned_user_id' => 'nullable|exists:users,id',
                'due_at' => 'nullable|date',
                'base_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();
            $template = TaskAutomationTemplate::find($data['template_id']);

            $dueAt = $data['due_at'] ?? $this->calculateDueAt($template, $data['base_at'] ?? null);
            if (!$dueAt) {
                return response()->json(['errors' => ['due_at' => ['Unable to resolve due_at from template.']]], 422);
            }

            $assignedUserId = $data['assigned_user_id'] ?? $this->getDefaultAdminId();

            $automation = TaskAutomation::create([
                'template_id' => $template->id,
                'trigger_event' => $data['trigger_event'] ?? $template->trigger_event,
                'related_type' => $data['related_type'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'assigned_user_id' => $assignedUserId,
                'due_at' => $dueAt,
                'status' => 'pending',
            ]);

            return response()->json($automation->load('template'), 201);
        } catch (\Exception $e) {
            Log::error('Task automation store error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['Admin', 'Partner'], true)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $automation = TaskAutomation::find($id);
            if (!$automation) {
                return response()->json(['error' => 'Automation not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'due_at' => 'sometimes|date',
                'status' => 'sometimes|in:pending,processing,completed,failed,canceled',
                'assigned_user_id' => 'sometimes|nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $automation->update($validator->validated());

            return response()->json($automation->fresh('template'));
        } catch (\Exception $e) {
            Log::error('Task automation update error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['Admin', 'Partner'], true)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $automation = TaskAutomation::find($id);
            if (!$automation) {
                return response()->json(['error' => 'Automation not found'], 404);
            }

            $automation->delete();

            return response()->json(['message' => 'Automation deleted']);
        } catch (\Exception $e) {
            Log::error('Task automation delete error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function calculateDueAt(TaskAutomationTemplate $template, ?string $baseAt = null): ?Carbon
    {
        $now = $baseAt ? Carbon::parse($baseAt) : Carbon::now();

        if ($template->schedule_type === 'relative') {
            if (!$template->delay_value || !$template->delay_unit) {
                return null;
            }

            return match ($template->delay_unit) {
                'minutes' => $now->copy()->addMinutes($template->delay_value),
                'hours' => $now->copy()->addHours($template->delay_value),
                'days' => $now->copy()->addDays($template->delay_value),
                'weeks' => $now->copy()->addWeeks($template->delay_value),
                default => null,
            };
        }

        if ($template->schedule_type === 'fixed') {
            return $template->fixed_at ? Carbon::parse($template->fixed_at) : null;
        }

        if ($template->schedule_type === 'recurring') {
            if (empty($template->cron_expression)) {
                return null;
            }
            $cron = CronExpression::factory($template->cron_expression);
            return Carbon::instance($cron->getNextRunDate($now, 0, true));
        }

        return null;
    }

    private function getDefaultAdminId(): ?int
    {
        return User::where('role', 'Admin')
            ->where('status', 'Active')
            ->value('id');
    }
}
