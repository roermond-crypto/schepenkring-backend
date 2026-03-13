<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\YachtshiftImportService;

class YachtshiftImportController extends Controller
{
    protected $importService;

    public function __construct(YachtshiftImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Store (Import) boats from provided Yachtshift XML URLs
     * POST /api/admin/yachts/bulk-import
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'urls' => 'required|array|min:1',
            'urls.*' => 'required|url'
        ]);

        $totalImported = 0;
        $totalUpdated = 0;
        $totalErrors = 0;
        $totalSkipped = 0;
        $results = [];

        foreach ($validated['urls'] as $url) {
            $result = $this->importService->importFromUrl($url);
            
            $results[] = [
                'url' => $url,
                'result' => $result
            ];

            $totalImported += $result['imported'];
            $totalUpdated += $result['updated'];
            $totalErrors += $result['errors'];
            $totalSkipped += $result['skipped'] ?? 0;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully processed feeds. Imported: {$totalImported}, Updated: {$totalUpdated}, Errors: {$totalErrors}, Skipped: {$totalSkipped}",
            'imported' => $totalImported,
            'updated' => $totalUpdated,
            'errors' => $totalErrors,
            'skipped' => $totalSkipped,
            'details' => $results
        ]);
    }
}
