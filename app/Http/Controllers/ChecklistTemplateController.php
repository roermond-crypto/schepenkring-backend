<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChecklistTemplate;

class ChecklistTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = ChecklistTemplate::with('items')->where('active', true);

        if ($request->has('boat_type')) {
            // Note: The frontend might pass `boat_type` string, but we use `boat_type_id`
            // Let's support both query params just in case
            $boatTypeId = $request->input('boat_type_id') ?? $request->input('boat_type');
            
            $query->where(function($q) use ($boatTypeId) {
                $q->where('boat_type_id', $boatTypeId)
                  ->orWhereNull('boat_type_id');
            });
        } else {
            $query->whereNull('boat_type_id');
        }

        return response()->json($query->get());
    }
}
