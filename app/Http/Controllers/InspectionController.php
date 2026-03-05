<?php

namespace App\Http\Controllers;

use App\Models\BoatInspection;
use App\Models\InspectionAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // <-- ADD THIS

class InspectionController extends Controller
{
    // Get inspection for a boat (or create if not exists)
    public function showForBoat($boatId)
    {
        $inspection = BoatInspection::firstOrCreate(
            ['boat_id' => $boatId],
            ['user_id' => auth()->id()]
        );
        return response()->json($inspection->load('answers.question'));
    }

    // Update an answer (human override, accept, verify)
    public function updateAnswer(Request $request, $inspectionId, $answerId)
    {
        $answer = InspectionAnswer::where('inspection_id', $inspectionId)->findOrFail($answerId);
        $answer->update($request->only(['human_answer', 'review_status']));
        return response()->json($answer);
    }

    /**
     * Create a new inspection for a boat.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'boat_id' => 'required|exists:yachts,id',
        ]);

        $inspection = BoatInspection::create([
            'boat_id' => $request->boat_id,
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);

        return response()->json($inspection, 201);
    }

    /**
     * Save answers for an inspection.
     */
    public function storeAnswers(Request $request, $id): JsonResponse
    {
        $inspection = BoatInspection::findOrFail($id);

        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:boat_check,id',
            'answers.*.answer' => 'required|string',
        ]);

        foreach ($request->answers as $ans) {
            InspectionAnswer::create([
                'inspection_id' => $inspection->id,
                'question_id' => $ans['question_id'],
                'human_answer' => $ans['answer'],
                'review_status' => 'accepted',
            ]);
        }

        return response()->json(['message' => 'Answers saved successfully.']);
    }
}