<?php

namespace App\Http\Controllers\Api\Tasks;

use App\Actions\Tasks\AcceptTaskAction;
use App\Actions\Tasks\AddTaskCommentAction;
use App\Actions\Tasks\CalendarTasksAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\DeleteTaskAction;
use App\Actions\Tasks\DeleteTaskAttachmentAction;
use App\Actions\Tasks\ListTaskActivitiesAction;
use App\Actions\Tasks\ListTasksAction;
use App\Actions\Tasks\MyTasksAction;
use App\Actions\Tasks\RejectTaskAction;
use App\Actions\Tasks\RemindTaskAction;
use App\Actions\Tasks\ReorderTasksAction;
use App\Actions\Tasks\RescheduleTaskAction;
use App\Actions\Tasks\ScheduleReminderAction;
use App\Actions\Tasks\ShowTaskAction;
use App\Actions\Tasks\UpdateTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Actions\Tasks\UploadTaskAttachmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tasks\TaskAttachmentRequest;
use App\Http\Requests\Api\Tasks\TaskCalendarRequest;
use App\Http\Requests\Api\Tasks\TaskCommentRequest;
use App\Http\Requests\Api\Tasks\TaskRemindRequest;
use App\Http\Requests\Api\Tasks\TaskReminderRequest;
use App\Http\Requests\Api\Tasks\TaskReorderRequest;
use App\Http\Requests\Api\Tasks\TaskRescheduleRequest;
use App\Http\Requests\Api\Tasks\TaskStatusRequest;
use App\Http\Requests\Api\Tasks\TaskStoreRequest;
use App\Http\Requests\Api\Tasks\TaskUpdateRequest;
use App\Models\TaskAttachment;
use App\Repositories\TaskRepository;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request, ListTasksAction $action)
    {
        $tasks = $action->execute($request->user());

        return response()->json($tasks);
    }

    public function myTasks(Request $request, MyTasksAction $action)
    {
        $tasks = $action->execute($request->user());

        return response()->json($tasks);
    }

    public function calendar(TaskCalendarRequest $request, CalendarTasksAction $action)
    {
        $tasks = $action->execute(
            $request->user(),
            $request->input('start'),
            $request->input('end')
        );

        return response()->json($tasks);
    }

    public function store(TaskStoreRequest $request, CreateTaskAction $action)
    {
        $task = $action->execute($request->user(), $request->validated());

        return response()->json($task, 201);
    }

    public function show(int $id, Request $request, ShowTaskAction $action)
    {
        $task = $action->execute($request->user(), $id);

        return response()->json($task);
    }

    public function update(TaskUpdateRequest $request, int $id, UpdateTaskAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $updated = $action->execute($request->user(), $task, $request->validated());

        return response()->json($updated);
    }

    public function destroy(int $id, Request $request, DeleteTaskAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $action->execute($request->user(), $task);

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function updateStatus(TaskStatusRequest $request, int $id, UpdateTaskStatusAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $updated = $action->execute($request->user(), $task, $request->validated()['status']);

        return response()->json($updated);
    }

    public function reschedule(TaskRescheduleRequest $request, int $id, RescheduleTaskAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $updated = $action->execute($request->user(), $task, $request->validated());

        return response()->json($updated);
    }

    public function scheduleReminder(TaskReminderRequest $request, int $id, ScheduleReminderAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $updated = $action->execute($request->user(), $task, $request->validated()['reminder_at'] ?? null);

        return response()->json($updated);
    }

    public function remind(TaskRemindRequest $request, int $id, RemindTaskAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $result = $action->execute($request->user(), $task, $request->validated());

        return response()->json($result);
    }

    public function reorder(TaskReorderRequest $request, ReorderTasksAction $action)
    {
        $action->execute($request->user(), $request->validated()['tasks']);

        return response()->json(['message' => 'Tasks reordered']);
    }

    public function accept(int $id, Request $request, AcceptTaskAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $updated = $action->execute($request->user(), $task);

        return response()->json($updated);
    }

    public function reject(int $id, Request $request, RejectTaskAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $updated = $action->execute($request->user(), $task);

        return response()->json($updated);
    }

    public function activities(int $id, Request $request, ListTaskActivitiesAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $result = $action->execute($request->user(), $task);

        return response()->json($result);
    }

    public function addComment(TaskCommentRequest $request, int $id, AddTaskCommentAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $log = $action->execute($request->user(), $task, $request->validated()['content']);

        return response()->json($log, 201);
    }

    public function uploadAttachment(TaskAttachmentRequest $request, int $id, UploadTaskAttachmentAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($id, $request->user());
        $attachment = $action->execute($request->user(), $task, $request->file('file'));

        return response()->json($attachment, 201);
    }

    public function deleteAttachment(int $taskId, int $attachmentId, Request $request, DeleteTaskAttachmentAction $action, TaskRepository $tasks)
    {
        $task = $tasks->findForUserOrFail($taskId, $request->user());
        $attachment = TaskAttachment::where('task_id', $taskId)->findOrFail($attachmentId);
        $action->execute($request->user(), $task, $attachment);

        return response()->json(['message' => 'Attachment deleted successfully']);
    }
}
