<?php

namespace App\Http\Controllers;

use App\Models\TaskAutomationTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TaskAutomationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['Admin', 'Partner'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = TaskAutomationTemplate::query();

        if ($request->filled('trigger_event')) {
            $query->where('trigger_event', $request->input('trigger_event'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['Admin', 'Partner'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $template = TaskAutomationTemplate::find($id);
        if (!$template) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        return response()->json($template);
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['Admin', 'Partner'], true)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'trigger_event' => 'required|string|max:255',
                'schedule_type' => 'required|in:relative,fixed,recurring',
                'delay_value' => 'required_if:schedule_type,relative|integer|min:1',
                'delay_unit' => 'required_if:schedule_type,relative|in:minutes,hours,days,weeks',
                'fixed_at' => 'required_if:schedule_type,fixed|date',
                'cron_expression' => 'required_if:schedule_type,recurring|string|max:255',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'priority' => 'required|in:Low,Medium,High,Urgent,Critical',
                'default_assignee_type' => 'required|in:admin,seller,buyer,harbor,creator,related_owner',
                'notification_enabled' => 'sometimes|boolean',
                'email_enabled' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $template = TaskAutomationTemplate::create($validator->validated());

            return response()->json($template, 201);
        } catch (\Exception $e) {
            Log::error('Task automation template store error: ' . $e->getMessage());
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

            $template = TaskAutomationTemplate::find($id);
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|nullable|string|max:255',
                'trigger_event' => 'sometimes|string|max:255',
                'schedule_type' => 'sometimes|in:relative,fixed,recurring',
                'delay_value' => 'required_if:schedule_type,relative|integer|min:1',
                'delay_unit' => 'required_if:schedule_type,relative|in:minutes,hours,days,weeks',
                'fixed_at' => 'required_if:schedule_type,fixed|date',
                'cron_expression' => 'required_if:schedule_type,recurring|string|max:255',
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'priority' => 'sometimes|in:Low,Medium,High,Urgent,Critical',
                'default_assignee_type' => 'sometimes|in:admin,seller,buyer,harbor,creator,related_owner',
                'notification_enabled' => 'sometimes|boolean',
                'email_enabled' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $template->update($validator->validated());

            return response()->json($template->fresh());
        } catch (\Exception $e) {
            Log::error('Task automation template update error: ' . $e->getMessage());
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

            $template = TaskAutomationTemplate::find($id);
            if (!$template) {
                return response()->json(['error' => 'Template not found'], 404);
            }

            $template->delete();

            return response()->json(['message' => 'Template deleted']);
        } catch (\Exception $e) {
            Log::error('Task automation template delete error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
