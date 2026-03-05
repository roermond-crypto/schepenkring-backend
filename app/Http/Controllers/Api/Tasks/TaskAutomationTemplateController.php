<?php

namespace App\Http\Controllers\Api\Tasks;

use App\Actions\TaskAutomationTemplate\CreateTaskAutomationTemplateAction;
use App\Actions\TaskAutomationTemplate\DeleteTaskAutomationTemplateAction;
use App\Actions\TaskAutomationTemplate\ListTaskAutomationTemplatesAction;
use App\Actions\TaskAutomationTemplate\ShowTaskAutomationTemplateAction;
use App\Actions\TaskAutomationTemplate\UpdateTaskAutomationTemplateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tasks\TaskAutomationTemplateStoreRequest;
use App\Http\Requests\Api\Tasks\TaskAutomationTemplateUpdateRequest;
use App\Models\TaskAutomationTemplate;
use Illuminate\Http\Request;

class TaskAutomationTemplateController extends Controller
{
    public function index(Request $request, ListTaskAutomationTemplatesAction $action)
    {
        $templates = $action->execute($request->user(), $request->all());

        return response()->json($templates);
    }

    public function show(int $id, Request $request, ShowTaskAutomationTemplateAction $action)
    {
        $template = $action->execute($request->user(), $id);

        return response()->json($template);
    }

    public function store(TaskAutomationTemplateStoreRequest $request, CreateTaskAutomationTemplateAction $action)
    {
        $template = $action->execute($request->user(), $request->validated());

        return response()->json($template, 201);
    }

    public function update(TaskAutomationTemplateUpdateRequest $request, int $id, UpdateTaskAutomationTemplateAction $action)
    {
        $template = TaskAutomationTemplate::findOrFail($id);
        $updated = $action->execute($request->user(), $template, $request->validated());

        return response()->json($updated);
    }

    public function destroy(int $id, Request $request, DeleteTaskAutomationTemplateAction $action)
    {
        $template = TaskAutomationTemplate::findOrFail($id);
        $action->execute($request->user(), $template);

        return response()->json(['message' => 'Template deleted']);
    }
}
