<?php

namespace App\Services;

use App\Models\CopilotAction;

class CopilotActionExecutionService
{
    public function execute(CopilotAction $action, array $payload): array
    {
        $deeplink = $this->applyTemplate($action->route_template, $payload);

        if ($action->query_template) {
            $query = $this->applyTemplate($action->query_template, $payload);
            if ($query !== '') {
                $deeplink .= (str_contains($deeplink, '?') ? '&' : '?') . ltrim($query, '?');
            }
        }

        return [
            'execution_type' => 'deeplink',
            'deeplink' => $deeplink,
        ];
    }

    private function applyTemplate(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }
}
