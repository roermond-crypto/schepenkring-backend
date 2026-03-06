<?php

namespace App\Http\Controllers\Api;

use App\Actions\Leads\CreateLeadFromWidgetAction;
use App\Actions\Leads\UpdateLeadFromWidgetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Leads\LeadWidgetStoreRequest;
use App\Http\Requests\Api\Leads\LeadWidgetUpdateRequest;
use App\Repositories\LeadRepository;
use App\Services\IdempotencyService;
use Illuminate\Http\Request;

class PublicLeadController extends Controller
{
    public function store(LeadWidgetStoreRequest $request, CreateLeadFromWidgetAction $action, IdempotencyService $idempotency)
    {
        $idempotencyResult = $idempotency->begin($request);
        if ($idempotencyResult['status'] === 'missing') {
            $idempotencyResult = null;
        } else {
            if ($idempotencyResult['status'] === 'conflict') {
                return response()->json(['message' => 'Idempotency-Key reuse with different request.'], 409);
            }
            if ($idempotencyResult['status'] === 'processing') {
                return response()->json(['message' => 'Request already in progress.'], 409);
            }
            if ($idempotencyResult['status'] === 'replay') {
                return $idempotencyResult['response'];
            }
        }

        $result = $action->execute($request->validated());

        $response = response()->json([
            'lead' => $result['lead']->load(['location']),
            'conversation' => $result['conversation'],
            'message' => $result['message'],
        ], 201);

        if ($idempotencyResult && ! empty($idempotencyResult['record'])) {
            $idempotency->storeResponse($idempotencyResult['record'], $response);
        }

        return $response;
    }

    public function update(
        string $conversationId,
        LeadWidgetUpdateRequest $request,
        LeadRepository $leads,
        UpdateLeadFromWidgetAction $action
    ) {
        $lead = $leads->findByConversationId($conversationId);
        if (! $lead) {
            return response()->json(['message' => 'Lead not found.'], 404);
        }

        $updated = $action->execute($lead, $request->validated());

        return response()->json([
            'lead' => $updated->load(['location', 'conversation']),
        ]);
    }
}
