<?php

namespace App\Http\Controllers\Api;

use App\Actions\Leads\ListLeadsAction;
use App\Actions\Leads\ShowLeadAction;
use App\Actions\Leads\UpdateLeadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Leads\LeadIndexRequest;
use App\Http\Requests\Api\Leads\LeadUpdateRequest;
use App\Repositories\LeadRepository;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(LeadIndexRequest $request, ListLeadsAction $action)
    {
        $leads = $action->execute($request->user(), $request->validated());

        return response()->json($leads);
    }

    public function show(int $id, Request $request, ShowLeadAction $action)
    {
        $lead = $action->execute($request->user(), $id);

        return response()->json($lead);
    }

    public function update(int $id, LeadUpdateRequest $request, LeadRepository $leads, UpdateLeadAction $action)
    {
        $lead = $leads->findForUserOrFail($id, $request->user());
        $updated = $action->execute($request->user(), $lead, $request->validated());

        return response()->json($updated);
    }
}
