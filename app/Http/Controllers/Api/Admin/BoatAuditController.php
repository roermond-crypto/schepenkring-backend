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
        $query = BoatFieldChange::with(['user:id,name,email,avatar', 'yacht:id,boat_name', 'platform:id,name'])
            ->orderByDesc('created_at');

        // Filter by specific yacht
        if ($request->filled('yacht_id')) {
            $query->where('yacht_id', $request->input('yacht_id'));
        }

        // Filter by platform (AI Quality Dashboard deep-links) — "0" means
        // the unscoped/"General" bucket (platform_id IS NULL).
        if ($request->filled('platform_id')) {
            $platformId = $request->input('platform_id');
            if ($platformId === '0' || $platformId === 0) {
                $query->whereNull('platform_id');
            } else {
                $query->where('platform_id', $platformId);
            }
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

        if ($request->filled('direction')) {
            $query->where('meta', 'like', '%"direction":"' . addcslashes((string) $request->input('direction'), '%_\\') . '"%');
        }

        if ($request->filled('source')) {
            $query->where('meta', 'like', '%"source":"' . addcslashes((string) $request->input('source'), '%_\\') . '"%');
        }

        if ($request->filled('target')) {
            $query->where('meta', 'like', '%"target":"' . addcslashes((string) $request->input('target'), '%_\\') . '"%');
        }

        if ($request->filled('sync_type')) {
            $query->where('meta', 'like', '%"sync_type":"' . addcslashes((string) $request->input('sync_type'), '%_\\') . '"%');
        }

        if ($request->boolean('errors')) {
            $query->where(function ($builder) {
                $builder->where('correction_label', 'sync_error')
                    ->orWhere('meta', 'like', '%"error"%');
            });
        }

        if ($request->boolean('price_changes')) {
            $query->where('field_name', 'price')
                ->whereColumn('old_value', '!=', 'new_value');
        }

        // Custom pagination limit, default to 50
        $perPage = (int) $request->input('per_page', 50);

        return $query->paginate($perPage);
    }
}
