<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingQuestion;
use App\Models\OnboardingQuestionAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dynamic, admin-managed onboarding/profile questions for the seller and
 * buyer dashboards — additive to the existing hardcoded profile fields
 * (name, email, birth_date, company_name, kvk_number, address), not a
 * replacement for them. Those map to real, specifically-validated User/
 * profile columns with their own business logic (address autocomplete,
 * KVK handling, etc.); this covers the "admin wants to add one more
 * custom question" case without re-architecting the existing form.
 */
class OnboardingQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $audience = strtolower($user->type?->value ?? '');
        if (! in_array($audience, [OnboardingQuestion::AUDIENCE_SELLER, OnboardingQuestion::AUDIENCE_BUYER], true)) {
            return response()->json(['data' => []]);
        }

        $questions = OnboardingQuestion::query()
            ->active()
            ->forAudience($audience)
            ->with(['answers' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('step_key')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (OnboardingQuestion $question) => [
                'id' => $question->id,
                'step_key' => $question->step_key,
                'field_type' => $question->field_type,
                'label' => $question->label,
                'help_text' => $question->help_text,
                'placeholder' => $question->placeholder,
                'options' => $question->options,
                'required' => $question->required,
                'sort_order' => $question->sort_order,
                'answer' => $question->answers->first()?->answer,
            ]);

        return response()->json(['data' => $questions]);
    }

    public function storeAnswers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'nullable|string',
        ]);

        $user = $request->user();
        $audience = strtolower($user->type?->value ?? '');

        $questionIds = array_map('intval', array_keys($validated['answers']));
        $questions = OnboardingQuestion::query()
            ->whereIn('id', $questionIds)
            ->forAudience($audience)
            ->get()
            ->keyBy('id');

        foreach ($validated['answers'] as $questionId => $answer) {
            $question = $questions->get((int) $questionId);
            if (! $question) {
                continue; // not a real question for this user's audience — ignore silently
            }

            OnboardingQuestionAnswer::updateOrCreate(
                ['user_id' => $user->id, 'onboarding_question_id' => $question->id],
                ['answer' => $answer],
            );
        }

        return response()->json(['message' => 'Answers saved.']);
    }
}
