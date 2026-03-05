<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AliasAdminController extends Controller
{
    /**
     * Get pending unmapped brands
     */
    public function getPendingBrands()
    {
        $aliases = DB::table('brand_aliases')
            ->whereNull('brand_id')
            ->where('is_reviewed', false)
            ->orderByDesc('evidence_count')
            ->get();
            
        return response()->json($aliases);
    }

    /**
     * Map a pending brand alias to a canonical brand
     */
    public function mapBrand(Request $request, $aliasId)
    {
        $request->validate(['brand_id' => 'required|exists:brands,id']);
        
        DB::table('brand_aliases')->where('id', $aliasId)->update([
            'brand_id' => $request->brand_id,
            'is_reviewed' => true,
            'updated_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Get pending unmapped models
     */
    public function getPendingModels(Request $request)
    {
        $query = DB::table('model_aliases')
            ->leftJoin('brands', 'model_aliases.brand_id', '=', 'brands.id')
            ->select('model_aliases.*', 'brands.name as brand_name')
            ->whereNull('model_id')
            ->where('model_aliases.is_reviewed', false)
            ->orderByDesc('evidence_count');
            
        return response()->json($query->get());
    }

    /**
     * Map a pending model alias to a canonical model
     */
    public function mapModel(Request $request, $aliasId)
    {
        $request->validate(['model_id' => 'required|exists:models,id']);
        
        DB::table('model_aliases')->where('id', $aliasId)->update([
            'model_id' => $request->model_id,
            'is_reviewed' => true,
            'updated_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Quickly discard an alias as "junk" (marks reviewed but leaves ID null)
     */
    public function discardAlias(Request $request, string $type, $aliasId)
    {
        $allowedTypes = ['brand_aliases', 'model_aliases', 'boat_type_aliases', 'engine_brand_aliases'];
        if (!in_array($type, $allowedTypes)) {
            return response()->json(['error' => 'Invalid alias table type'], 400);
        }

        DB::table($type)->where('id', $aliasId)->update([
            'is_reviewed' => true,
            'updated_at' => now()
        ]);

        return response()->json(['success' => true]);
    }
}
