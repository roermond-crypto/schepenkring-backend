<?php

namespace App\Http\Controllers\Api\Tasks;

use App\Actions\TaskAutomation\CreateTaskAutomationAction;
use App\Actions\TaskAutomation\DeleteTaskAutomationAction;
use App\Actions\TaskAutomation\ListTaskAutomationsAction;
use App\Actions\TaskAutomation\ShowTaskAutomationAction;
use App\Actions\TaskAutomation\UpdateTaskAutomationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tasks\TaskAutomationStoreRequest;
use App\Http\Requests\Api\Tasks\TaskAutomationUpdateRequest;
use App\Models\TaskAutomation;
use Illuminate\Http\Request;

class TaskAutomationController extends Controller
{
    public function index(Request $request, ListTaskAutomationsAction $action)
    {
        $automations = $action->execute($request->user(), $request->all());

        return response()->json($automations);
    }

    public function show(int $id, Request $request, ShowTaskAutomationAction $action)
    {
        $automation = $action->execute($request->user(), $id);

        return response()->json($automation);
    }

    public function store(TaskAutomationStoreRequest $request, CreateTaskAutomationAction $action)
    {
        $automation = $action->execute($request->user(), $request->validated());

        return response()->json($automation, 201);
    }

    public function update(TaskAutomationUpdateRequest $request, int $id, UpdateTaskAutomationAction $action)
    {
        $automation = TaskAutomation::findOrFail($id);
        $updated = $action->execute($request->user(), $automation, $request->validated());

        return response()->json($updated);
    }

    public function destroy(int $id, Request $request, DeleteTaskAutomationAction $action)
    {
        $automation = TaskAutomation::findOrFail($id);
        $action->execute($request->user(), $automation);

        return response()->json(['message' => 'Automation deleted']);
    }
}
