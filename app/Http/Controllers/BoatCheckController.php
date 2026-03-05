<?php

namespace App\Http\Controllers;

use App\Models\BoatCheck;
use App\Http\Requests\StoreBoatCheckRequest;
use Illuminate\Http\JsonResponse;

class BoatCheckController extends Controller
{
    /**
     * Display a listing of the checklist questions.
     */
    public function index(): JsonResponse
    {
        $questions = BoatCheck::with('boatTypes')->orderBy('id', 'desc')->get();
        return response()->json($questions);
    }

    /**
     * Store a newly created checklist question.
     */
    public function store(StoreBoatCheckRequest $request): JsonResponse
    {
        $data = $request->only([
            'question_text',
            'type',
            'options',
            'required',
            'ai_prompt',
            'evidence_sources',
            'weight',
        ]);

        // Default required to false if not provided
        if (!isset($data['required'])) {
            $data['required'] = false;
        }

        $boatCheck = BoatCheck::create($data);

        if ($request->has('boat_type_ids')) {
            $boatCheck->boatTypes()->sync($request->boat_type_ids);
        }

        $boatCheck->load('boatTypes');

        return response()->json($boatCheck, 201);
    }

    /**
     * Display the specified checklist question.
     */
    public function show($id): JsonResponse
    {
        $boatCheck = BoatCheck::with('boatTypes')->findOrFail($id);
        return response()->json($boatCheck);
    }

    /**
     * Update the specified checklist question.
     */
    public function update(StoreBoatCheckRequest $request, $id): JsonResponse
    {
        $boatCheck = BoatCheck::findOrFail($id);

        $data = $request->only([
            'question_text',
            'type',
            'options',
            'required',
            'ai_prompt',
            'evidence_sources',
            'weight',
        ]);

        $boatCheck->update($data);

        if ($request->has('boat_type_ids')) {
            $boatCheck->boatTypes()->sync($request->boat_type_ids);
        } else {
            // If no boat_type_ids provided, detach all (meaning generic)
            $boatCheck->boatTypes()->detach();
        }

        $boatCheck->load('boatTypes');

        return response()->json($boatCheck);
    }

    /**
     * Remove the specified checklist question.
     */
    public function destroy($id): JsonResponse
    {
        $boatCheck = BoatCheck::findOrFail($id);
        $boatCheck->boatTypes()->detach();
        $boatCheck->delete();

        return response()->json(['message' => 'Checklist question deleted successfully.']);
    }
}