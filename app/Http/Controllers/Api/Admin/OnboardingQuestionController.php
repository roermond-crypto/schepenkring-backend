<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => 'nullable|string|in:seller,buyer,both',
            'step_key' => 'nullable|string|max:80',
        ]);

        $query = OnboardingQuestion::query()->orderBy('step_key')->orderBy('sort_order');

        if (! empty($validated['audience'])) {
            $query->where('audience', $validated['audience']);
        }

        if (! empty($validated['step_key'])) {
            $query->where('step_key', $validated['step_key']);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $question = OnboardingQuestion::create($validated);

        return response()->json(['data' => $question], 201);
    }

    public function update(Request $request, OnboardingQuestion $onboardingQuestion): JsonResponse
    {
        $validated = $this->validated($request);

        $onboardingQuestion->update($validated);

        return response()->json(['data' => $onboardingQuestion->fresh()]);
    }

    public function destroy(OnboardingQuestion $onboardingQuestion): JsonResponse
    {
        $onboardingQuestion->delete();

        return response()->json(['message' => 'Question deleted.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:onboarding_questions,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['items'] as $item) {
            OnboardingQuestion::whereKey($item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Order updated.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'audience' => ['required', Rule::in([
                OnboardingQuestion::AUDIENCE_SELLER,
                OnboardingQuestion::AUDIENCE_BUYER,
                OnboardingQuestion::AUDIENCE_BOTH,
            ])],
            'step_key' => 'required|string|max:80',
            'field_type' => ['required', Rule::in(OnboardingQuestion::FIELD_TYPES)],
            'label' => 'required|array',
            'help_text' => 'nullable|array',
            'placeholder' => 'nullable|array',
            'options' => 'nullable|array',
            'options.*.value' => 'required_with:options|string|max:150',
            'options.*.label' => 'required_with:options|array',
            'required' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'active' => 'nullable|boolean',
        ]);
    }
}
