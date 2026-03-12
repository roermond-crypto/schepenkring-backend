<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoatFieldChange;
use Illuminate\Http\Request;

class BoatAuditController extends Controller
{
    /**
     * Get a paginated list of all boat field changes (audit logs for yacht attributes).
     */
    public function index(Request $request)
    {
        $query = BoatFieldChange::with(['user:id,name,email,avatar', 'yacht:id,boat_name'])
            ->orderByDesc('created_at');

        // Filter by specific yacht
        if ($request->filled('yacht_id')) {
            $query->where('yacht_id', $request->input('yacht_id'));
        }

        // Filter by field name
        if ($request->filled('field_name')) {
            $query->where('field_name', 'like', '%' . $request->input('field_name') . '%');
        }

        // Filter by user/actor
        if ($request->filled('user_id')) {
            $query->where('changed_by_id', $request->input('user_id'));
        }

        // Filter by actor type (e.g. 'ai', 'user', 'admin')
        if ($request->filled('changed_by_type')) {
            $query->where('changed_by_type', $request->input('changed_by_type'));
        }

        // Filter by correction label (feedback loop tags)
        if ($request->filled('correction_label')) {
            $query->where('correction_label', $request->input('correction_label'));
        }

        // Filter by reason
        if ($request->filled('reason')) {
            $query->where('reason', 'like', '%' . $request->input('reason') . '%');
        }

        // Custom pagination limit, default to 50
        $perPage = (int) $request->input('per_page', 50);

        return $query->paginate($perPage);
    }
}
