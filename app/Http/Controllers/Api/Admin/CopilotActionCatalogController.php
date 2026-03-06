<?php

namespace App\Http\Controllers\Api\Admin;

use App\Services\CopilotActionCatalogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CopilotActionCatalogController extends Controller
{
    public function __construct(private CopilotActionCatalogService $catalog)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json($this->catalog->buildCatalog());
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Unauthorized');
        }
        if (! $user->isAdmin()) {
            abort(403, 'Forbidden');
        }
    }
}
