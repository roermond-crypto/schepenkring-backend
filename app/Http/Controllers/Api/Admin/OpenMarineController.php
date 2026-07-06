<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Yacht;
use App\Services\OpenMarineService;
use Illuminate\Http\JsonResponse;

class OpenMarineController extends Controller
{
    public function __construct(private OpenMarineService $openMarine) {}

    /**
     * POST /admin/yachts/{yacht}/open-marine/generate
     *
     * Generates OpenMarine 2.0 XML, validates it, and returns xml + validation result.
     */
    public function generate(Yacht $yacht): JsonResponse
    {
        $yacht->loadMissing('images', 'location');

        $result = $this->openMarine->generate($yacht);

        // Separate field-missing errors into their own key so the frontend can
        // display them distinctly from structural validation errors.
        $missingRequired = array_values(array_filter(
            $result['errors'],
            fn (string $e) => str_starts_with($e, 'Required field missing:')
        ));
        $otherErrors = array_values(array_filter(
            $result['errors'],
            fn (string $e) => ! str_starts_with($e, 'Required field missing:')
        ));

        return response()->json([
            'xml'        => $result['xml'],
            'validation' => [
                'valid'            => $result['valid'],
                'errors'           => $otherErrors,
                'warnings'         => $result['warnings'],
                'missing_required' => $missingRequired,
            ],
        ]);
    }
}
