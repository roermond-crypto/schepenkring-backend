<?php

namespace App\Repositories;

use App\Models\TaskAutomationRule;

class TaskAutomationRuleRepository
{
    public function query()
    {
        return TaskAutomationRule::query()->with(['templates.items', 'assignedUser:id,name,email,type,role']);
    }

    public function findOrFail(int $id): TaskAutomationRule
    {
        return TaskAutomationRule::with(['templates.items', 'assignedUser:id,name,email,type,role'])->findOrFail($id);
    }

    public function create(array $data, array $templateIds = []): TaskAutomationRule
    {
        $rule = TaskAutomationRule::create($data);
        $this->syncTemplates($rule, $templateIds);

        return $rule->load(['templates.items', 'assignedUser:id,name,email,type,role']);
    }

    public function update(TaskAutomationRule $rule, array $data, ?array $templateIds = null): TaskAutomationRule
    {
        $rule->fill($data);
        $rule->save();

        if (is_array($templateIds)) {
            $this->syncTemplates($rule, $templateIds);
        }

        return $rule->fresh(['templates.items', 'assignedUser:id,name,email,type,role']);
    }

    public function delete(TaskAutomationRule $rule): void
    {
        $rule->delete();
    }

    private function syncTemplates(TaskAutomationRule $rule, array $templateIds): void
    {
        $sync = [];

        foreach (array_values($templateIds) as $index => $templateId) {
            $sync[(int) $templateId] = ['sort_order' => $index];
        }

        $rule->templates()->sync($sync);
    }
}
