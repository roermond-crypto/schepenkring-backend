<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $query = Location::query();

        $includeInactive = filter_var($request->query('include_inactive'), FILTER_VALIDATE_BOOL);
        if (! $includeInactive) {
            $query->where('status', 'ACTIVE');
        }

        return response()->json($query->orderBy('name')->get());
    }
}
