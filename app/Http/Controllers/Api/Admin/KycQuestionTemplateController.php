<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycQuestionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycQuestionTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $questions = KycQuestionTemplate::orderBy('section')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $questions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section'                    => 'required|string|max:100',
            'question'                   => 'required|string|max:500',
            'action'                     => 'nullable|string|max:500',
            'translations'               => 'nullable|array',
            'translations.*.section'     => 'nullable|string|max:100',
            'translations.*.question'    => 'nullable|string|max:500',
            'translations.*.action'      => 'nullable|string|max:500',
            'field_type'                 => 'required|in:yes_no,dropdown,text,textarea,date,number,money,country,document,photo,signature',
            'options'                    => 'nullable|array',
            'risk_points'                => 'required|integer|min:0',
            'risk_flag'                  => 'required|in:none,warning,blocking,critical',
            'required'                   => 'required|boolean',
            'conditional_on_question_id' => 'nullable|integer|exists:kyc_question_templates,id',
            'conditional_show_when'      => 'nullable|string|max:50',
            'sort_order'                 => 'nullable|integer|min:0',
            'active'                     => 'required|boolean',
        ]);

        $question = KycQuestionTemplate::create($data);

        return response()->json(['data' => $question], 201);
    }

    public function update(Request $request, KycQuestionTemplate $question): JsonResponse
    {
        $data = $request->validate([
            'section'                    => 'sometimes|required|string|max:100',
            'question'                   => 'sometimes|required|string|max:500',
            'action'                     => 'nullable|string|max:500',
            'translations'               => 'nullable|array',
            'translations.*.section'     => 'nullable|string|max:100',
            'translations.*.question'    => 'nullable|string|max:500',
            'translations.*.action'      => 'nullable|string|max:500',
            'field_type'                 => 'sometimes|required|in:yes_no,dropdown,text,textarea,date,number,money,country,document,photo,signature',
            'options'                    => 'nullable|array',
            'risk_points'                => 'sometimes|required|integer|min:0',
            'risk_flag'                  => 'sometimes|required|in:none,warning,blocking,critical',
            'required'                   => 'sometimes|required|boolean',
            'conditional_on_question_id' => 'nullable|integer|exists:kyc_question_templates,id',
            'conditional_show_when'      => 'nullable|string|max:50',
            'sort_order'                 => 'nullable|integer|min:0',
            'active'                     => 'sometimes|required|boolean',
        ]);

        $question->update($data);

        return response()->json(['data' => $question->fresh()]);
    }

    public function destroy(KycQuestionTemplate $question): JsonResponse
    {
        $question->delete();

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'              => 'required|array',
            'items.*.id'         => 'required|integer|exists:kyc_question_templates,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($data['items'] as $item) {
            KycQuestionTemplate::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true]);
    }
}
