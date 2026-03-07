<?php

namespace App\Http\Controllers\Api;

use App\Actions\Leads\ConvertLeadToClientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Leads\LeadConvertRequest;
use App\Repositories\LeadRepository;

class LeadConversionController extends Controller
{
    public function store(int $id, LeadConvertRequest $request, LeadRepository $leads, ConvertLeadToClientAction $action)
    {
        $lead = $leads->findForUserOrFail($id, $request->user());
        $client = $action->execute(
            $request->user(),
            $lead,
            $request->header('Idempotency-Key') ?? $request->input('idempotency_key')
        );

        return response()->json([
            'client' => $client,
            'lead' => $lead->refresh(),
        ]);
    }
}
