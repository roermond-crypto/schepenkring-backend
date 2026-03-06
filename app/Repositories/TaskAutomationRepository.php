<?php

namespace App\Repositories;

use App\Models\TaskAutomation;
use Illuminate\Database\Eloquent\Builder;

class TaskAutomationRepository
{
    public function query(): Builder
    {
        return TaskAutomation::query()->with('template');
    }

    public function findOrFail(int $id): TaskAutomation
    {
        return TaskAutomation::with('template')->findOrFail($id);
    }

    public function create(array $data): TaskAutomation
    {
        return TaskAutomation::create($data);
    }

    public function update(TaskAutomation $automation, array $data): TaskAutomation
    {
        $automation->fill($data);
        $automation->save();

        return $automation;
    }

    public function delete(TaskAutomation $automation): void
    {
        $automation->delete();
    }
}
