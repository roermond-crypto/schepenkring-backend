<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VesselAnalytic;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function summary()
    {
        try {
            $stats = VesselAnalytic::select(
                'external_id',
                DB::raw('MAX(name) as name'),
                DB::raw('MAX(url) as url'),
                DB::raw('MAX(price) as last_price'),
                DB::raw('MAX(ref_code) as ref_code'),
                DB::raw('count(*) as total_views')
            )
            ->groupBy('external_id')
            ->orderBy('total_views', 'desc')
            ->limit(15)
            ->get();

            return response()->json($stats, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function track(Request $request)
    {
        // We capture everything from your script
        VesselAnalytic::create([
            'external_id' => $request->external_id,
            'name'        => $request->name,
            'model'       => $request->model ?? $request->slug, // Fallback to slug if model not found
            'price'       => $request->price,
            'ref_code'    => $request->ref_code,
            'url'         => $request->url,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'raw_specs'   => [
                'specs'      => $request->specs,
                'year'       => $request->year,
                'location'   => $request->location,
                'referrer'   => $request->referrer,
                'resolution' => $request->resolution,
                'language'   => $request->language
            ],
        ]);

        return response()->json(['status' => 'synced'], 200);
    }
}