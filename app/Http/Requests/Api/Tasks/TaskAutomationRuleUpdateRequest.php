<?php

namespace App\Http\Requests\Api\Tasks;

class TaskAutomationRuleUpdateRequest extends TaskAutomationRuleStoreRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $field => $fieldRules) {
            $rules[$field] = array_values(array_filter(
                array_merge(['sometimes'], (array) $fieldRules),
                static fn ($rule) => $rule !== 'required'
            ));
        }

        return $rules;
    }
}
