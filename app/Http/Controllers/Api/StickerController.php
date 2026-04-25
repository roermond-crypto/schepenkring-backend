<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Services\StickerService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class StickerController extends Controller
{
    public function __construct(protected StickerService $stickerService) {}

    /**
     * Preview the sticker HTML.
     */
    public function preview(Yacht $yacht)
    {
        $yacht = $this->stickerService->syncForYacht($yacht);

        return view('stickers.boat', $this->stickerService->getStickerViewData($yacht));
    }

    /**
     * Download the sticker as PDF.
     */
    public function downloadPdf(Yacht $yacht)
    {
        $pdf = $this->stickerService->generatePdf($yacht);

        $filename = 'sticker_' . Str::slug($yacht->boat_name ?: "yacht-{$yacht->id}") . "_{$yacht->vessel_id}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Force-generate or refresh the sticker assets for a yacht.
     */
    public function generate(Yacht $yacht)
    {
        return response()->json(
            $this->stickerService->syncForYacht($yacht, true)
        );
    }
}
