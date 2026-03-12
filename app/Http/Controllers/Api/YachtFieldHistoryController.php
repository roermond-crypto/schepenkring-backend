<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiCorrectionLoggingService;
use Illuminate\Http\Request;

class YachtFieldHistoryController extends Controller
{
    protected $loggingService;

    public function __construct(AiCorrectionLoggingService $loggingService)
    {
        $this->loggingService = $loggingService;
    }

    /**
     * Get history for a specific field of a yacht.
     */
    public function show(int $yachtId, string $fieldName)
    {
        $history = $this->loggingService->getFieldHistory($yachtId, $fieldName);

        return response()->json([
            'field' => $fieldName,
            'yacht_id' => $yachtId,
            'history' => $history
        ]);
    }
}
