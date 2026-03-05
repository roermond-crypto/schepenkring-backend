<?php

namespace App\Repositories;

use App\Models\TaskAutomationTemplate;
use Illuminate\Database\Eloquent\Builder;

class TaskAutomationTemplateRepository
{
    public function query(): Builder
    {
        return TaskAutomationTemplate::query();
    }

    public function findOrFail(int $id): TaskAutomationTemplate
    {
        return TaskAutomationTemplate::findOrFail($id);
    }

    public function create(array $data): TaskAutomationTemplate
    {
        return TaskAutomationTemplate::create($data);
    }

    public function update(TaskAutomationTemplate $template, array $data): TaskAutomationTemplate
    {
        $template->fill($data);
        $template->save();

        return $template;
    }

    public function delete(TaskAutomationTemplate $template): void
    {
        $template->delete();
    }
}
