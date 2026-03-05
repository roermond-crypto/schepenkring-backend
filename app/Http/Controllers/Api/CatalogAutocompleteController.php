<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogAutocompleteController extends Controller
{
    /**
     * Search Canonical Brands
     */
    public function searchBrands(Request $request)
    {
        $query = $request->get('q');
        
        $brands = DB::table('brands')
            ->when($query, function($q) use ($query) {
                return $q->where('name', 'like', "%{$query}%");
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(20)
            ->get();
            
        return response()->json($brands);
    }

    /**
     * Search Canonical Models (Dependent on Brand ID)
     */
    public function searchModels(Request $request)
    {
        $brandId = $request->get('brand_id');
        $query = $request->get('q');

        $models = DB::table('models')
            ->when($brandId, function($q) use ($brandId) {
                return $q->where('brand_id', $brandId);
            })
            ->when($query, function($q) use ($query) {
                return $q->where('name', 'like', "%{$query}%");
            })
            ->select('id', 'brand_id', 'name')
            ->orderBy('name')
            ->limit(20)
            ->get();
            
        return response()->json($models);
    }
    
    /**
     * Search Canonical Boat Types
     */
    public function searchBoatTypes(Request $request)
    {
        $query = $request->get('q');

        $types = DB::table('boat_types')
            ->when($query, function($q) use ($query) {
                return $q->where('name', 'like', "%{$query}%");
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($types);
    }
}
