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
            $query = $this->stripUnfilledQueryParams($query);
            if ($query !== '' && $query !== '?') {
                $deeplink .= (str_contains($deeplink, '?') ? '&' : '?') . ltrim($query, '?');
            }
        }

        return [
            'execution_type' => 'deeplink',
            'deeplink' => $deeplink,
            'unfilled_params' => $this->extractUnfilledParams($deeplink),
        ];
    }

    private function applyTemplate(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            // Skip null and non-scalar values to avoid replacing placeholders with "" or "Array"
            if ($value === null || is_array($value) || is_object($value)) {
                continue;
            }

            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    private function stripUnfilledQueryParams(string $queryString): string
    {
        $query = ltrim($queryString, '?');
        if ($query === '') {
            return '';
        }

        $parts = explode('&', $query);
        $kept = array_filter($parts, function (string $part): bool {
            // Drop pairs where the value still contains an unfilled {placeholder} or is empty
            [, $value] = array_pad(explode('=', $part, 2), 2, '');
            return $value !== '' && ! preg_match('/\{[^}]+\}/', urldecode($value));
        });

        return implode('&', $kept);
    }

    private function extractUnfilledParams(string $url): array
    {
        preg_match_all('/\{([^}]+)\}/', $url, $matches);
        return $matches[1] ?? [];
    }
}
