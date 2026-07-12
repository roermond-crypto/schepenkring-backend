<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpenMarineFieldMapping;
use App\Models\Yacht;
use App\Services\OpenMarineService;
use Illuminate\Http\JsonResponse;

class OpenMarineController extends Controller
{
    public function __construct(private OpenMarineService $openMarine) {}

    /**
     * GET /admin/openmarine/mappings
     *
     * Every Schepenkring field -> OpenMarine XML path, grouped, for the
     * Integration Center's reference view.
     */
    public function mappings(): JsonResponse
    {
        $grouped = OpenMarineFieldMapping::query()
            ->orderBy('group_label')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (OpenMarineFieldMapping $mapping) => $mapping->group_label ?? 'Other')
            ->map(fn ($rows) => $rows->values());

        return response()->json(['data' => $grouped]);
    }

    /**
     * GET /admin/openmarine/mappings/inspect/{yacht}
     *
     * Field-by-field export readiness for one yacht — every mapped field,
     * its current value, and whether it's populated, plus the same
     * errors/warnings validate() produces. Built for debugging "why isn't
     * this yacht exporting/why does it look thin on OpenMarine."
     */
    public function inspect(Yacht $yacht): JsonResponse
    {
        $yacht->loadMissing('images', 'location');

        [$errors, $warnings] = $this->openMarine->validate($yacht);

        return response()->json([
            'data' => [
                'yacht' => ['id' => $yacht->id, 'boat_name' => $yacht->boat_name, 'status' => $yacht->status],
                'fields' => $this->openMarine->inspectMapping($yacht),
                'errors' => $errors,
                'warnings' => $warnings,
            ],
        ]);
    }

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
