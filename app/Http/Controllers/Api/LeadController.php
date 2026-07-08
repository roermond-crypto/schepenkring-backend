<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\LocationAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LeadController extends Controller
{
    public function __construct(private LocationAccessService $locationAccess)
    {
    }

    public function index(Request $request)
    {
        $query = Lead::with(['conversation', 'location', 'assignedEmployee', 'convertedClient']);
        $query = $this->locationAccess->scopeQuery($query, $request->user());

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->paginate($request->input('per_page', 20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'location_id' => 'sometimes|integer|exists:locations,id|nullable',
            'source' => 'required|string',
            'status' => 'required|string',
            'name' => 'sometimes|string|nullable',
            'email' => 'sometimes|email|nullable',
            'phone' => 'sometimes|string|nullable',
            'notes' => 'sometimes|string|nullable',
        ]);

        // Employees can only create leads for a location they're assigned to.
        if (! $this->locationAccess->sharesLocation($request->user(), $validated['location_id'] ?? null)) {
            abort(403, 'You do not have access to this location.');
        }

        $lead = Lead::create($validated);
        $lead->load(['conversation', 'location', 'assignedEmployee', 'convertedClient']);

        return response()->json($lead, 201);
    }

    public function show(Request $request, $id)
    {
        $lead = $this->findAccessibleLead($request, $id);
        $lead->load(['conversation', 'location', 'assignedEmployee', 'convertedClient']);

        return $lead;
    }

    public function update(Request $request, $id)
    {
        $lead = $this->findAccessibleLead($request, $id);

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

    /**
     * Fetch a lead by ID, but only if the requesting user's location(s) grant access.
     * Returns a 404 (not 403) for out-of-scope leads so their existence isn't leaked.
     */
    private function findAccessibleLead(Request $request, $id): Lead
    {
        $lead = Lead::findOrFail($id);

        if (! $this->locationAccess->sharesLocation($request->user(), $lead->location_id)) {
            throw new NotFoundHttpException();
        }

        return $lead;
    }
}
