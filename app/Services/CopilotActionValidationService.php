<?php

namespace App\Services;

use App\Models\CopilotAction;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CopilotActionValidationService
{
    public function __construct(private CopilotPermissionService $permissionService)
    {
    }

    public function validateAction(User $user, CopilotAction $action, array $payload): array
    {
        if (! $this->permissionService->canUseAction($user, $action->permission_key, $action->required_role)) {
            return [
                'ok' => false,
                'errors' => ['permission' => ['Forbidden']],
            ];
        }

        $schema = $action->input_schema;
        if (is_array($schema)) {
            $rules = $this->rulesFromSchema($schema);
            $validator = Validator::make($payload, $rules);
            if ($validator->fails()) {
                return [
                    'ok' => false,
                    'errors' => $validator->errors()->toArray(),
                ];
            }
        } else {
            $required = $action->required_params ?? [];
            foreach ($required as $param) {
                if (!array_key_exists($param, $payload)) {
                    return [
                        'ok' => false,
                        'errors' => [$param => ['Required']],
                    ];
                }
            }
        }

        $securityCheck = $this->checkSecurity($user, $action);
        if ($securityCheck !== null) {
            return [
                'ok' => false,
                'errors' => $securityCheck,
            ];
        }

        return [
            'ok' => true,
            'errors' => [],
        ];
    }

    private function checkSecurity(User $user, CopilotAction $action): ?array
    {
        $level = $action->risk_level ?: 'low';
        $freshMinutes = $action->fresh_auth_required_minutes;

        if ($level !== 'high' && $freshMinutes === null) {
            return null;
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return ['auth' => ['Unauthorized']];
        }

        $requiredStrength = (string) (config('security.levels.high.required_strength') ?? 'otp');
        $currentStrength = (string) ($token->auth_strength ?? 'password');

        if (!$this->meetsRequirement($currentStrength, $requiredStrength)) {
            return ['auth' => ['Step-up verification required']];
        }

        $freshMinutes = $freshMinutes ?? config('security.levels.high.fresh_minutes');
        if ($freshMinutes !== null) {
            $lastVerified = $token->last_verified_at ?? $token->created_at;
            if (!$lastVerified || $lastVerified->diffInMinutes(now()) > (int) $freshMinutes) {
                return ['auth' => ["Recent verification required ({$freshMinutes}m)"]];
            }
        }

        return null;
    }

    private function rulesFromSchema(array $schema): array
    {
        $rules = [];

        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        foreach ($properties as $field => $definition) {
            $fieldRules = [];
            $type = $definition['type'] ?? null;
            if ($type === 'integer') {
                $fieldRules[] = 'integer';
            } elseif ($type === 'number') {
                $fieldRules[] = 'numeric';
            } elseif ($type === 'boolean') {
                $fieldRules[] = 'boolean';
            } elseif ($type === 'array') {
                $fieldRules[] = 'array';
            } else {
                $fieldRules[] = 'string';
            }

            if (in_array($field, $required, true)) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if (isset($definition['minLength'])) {
                $fieldRules[] = 'min:' . (int) $definition['minLength'];
            }
            if (isset($definition['maxLength'])) {
                $fieldRules[] = 'max:' . (int) $definition['maxLength'];
            }
            if (isset($definition['minimum'])) {
                $fieldRules[] = 'min:' . (int) $definition['minimum'];
            }
            if (isset($definition['maximum'])) {
                $fieldRules[] = 'max:' . (int) $definition['maximum'];
            }
            if (isset($definition['enum']) && is_array($definition['enum'])) {
                $fieldRules[] = 'in:' . implode(',', $definition['enum']);
            }
            if (($definition['format'] ?? null) === 'url') {
                $fieldRules[] = 'url';
            }

            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    private function meetsRequirement(string $current, string $required): bool
    {
        $order = [
            'password' => 1,
            'otp' => 2,
            'mfa' => 3,
            'passkey' => 4,
        ];

        $currentRank = $order[$current] ?? 0;
        $requiredRank = $order[$required] ?? 1;

        return $currentRank >= $requiredRank;
    }
}
