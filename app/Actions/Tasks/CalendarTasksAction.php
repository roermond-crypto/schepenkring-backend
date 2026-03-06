<?php

namespace App\Actions\Tasks;

use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Support\Collection;

class CalendarTasksAction
{
    public function __construct(private TaskRepository $tasks)
    {
    }

    public function execute(User $user, ?string $start, ?string $end): Collection
    {
        $query = $this->tasks->queryForUser($user);

        if ($start && $end) {
            $query->whereBetween('due_date', [$start, $end]);
        }

        return $query->get()->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'start' => $task->due_date,
                'end' => $task->due_date ? (new \DateTime($task->due_date))->modify('+1 day')->format('Y-m-d') : null,
                'priority' => $task->priority,
                'status' => $task->status,
                'type' => $task->type,
                'assigned_to' => $task->assignedTo?->name,
                'yacht' => $task->yacht?->name,
                'color' => $this->getPriorityColor($task->priority),
            ];
        });
    }

    private function getPriorityColor(string $priority): string
    {
        return match ($priority) {
            'Critical' => '#dc2626',
            'Urgent' => '#ea580c',
            'High' => '#d97706',
            'Medium' => '#3b82f6',
            'Low' => '#6b7280',
            default => '#6b7280',
        };
    }
}
