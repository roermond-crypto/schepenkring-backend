<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $query = Lead::with(['conversation', 'location', 'assignedEmployee', 'convertedClient']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->paginate($request->input('per_page', 20));
    }

    public function show($id)
    {
        return Lead::with(['conversation', 'location', 'assignedEmployee', 'convertedClient'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'sometimes|string',
            'assigned_employee_id' => 'nullable|exists:users,id',
            'name' => 'sometimes|string|nullable',
            'email' => 'sometimes|email|nullable',
            'phone' => 'sometimes|string|nullable',
            'notes' => 'sometimes|string|nullable',
        ]);

        $lead->update($validated);

        // Load relationships to match expected response shape
        $lead->load(['conversation', 'location', 'assignedEmployee', 'convertedClient']);

        return response()->json($lead);
    }
}
