<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Boat;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Location;
use App\Models\LocationDeleteRequest;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LocationDeleteApprovalController extends Controller
{
    /**
     * GET /locations/delete/approve/{token}
     * Clicked from the approval email — no auth required (token is the secret).
     */
    public function approve(string $token, Request $request): Response
    {
        $deleteRequest = LocationDeleteRequest::with(['location', 'requestedBy'])
            ->where('token', $token)
            ->first();

        if (! $deleteRequest) {
            return $this->htmlPage(
                'Ongeldig verzoek',
                'Dit verwijderingsverzoek bestaat niet of is al verwerkt.',
                '#C8102E',
                false
            );
        }

        if ($deleteRequest->status !== 'pending') {
            return $this->htmlPage(
                'Al verwerkt',
                "Dit verzoek heeft al de status: <strong>{$deleteRequest->status}</strong>.",
                '#64748b',
                false
            );
        }

        if ($deleteRequest->isExpired()) {
            $deleteRequest->update(['status' => 'expired']);

            return $this->htmlPage(
                'Verlopen',
                'Dit goedkeuringslink is verlopen (geldig 24 uur).',
                '#C8102E',
                false
            );
        }

        $location = $deleteRequest->location;

        if (! $location) {
            return $this->htmlPage(
                'Niet gevonden',
                'De locatie kon niet worden gevonden.',
                '#C8102E',
                false
            );
        }

        DB::beginTransaction();

        try {
            // Move records to another location if requested
            if ($deleteRequest->move_to_location_id) {
                $this->moveRecords(
                    (int) $location->id,
                    (int) $deleteRequest->move_to_location_id
                );
            }

            // Soft-delete the location
            $location->update([
                'deleted_by'    => $deleteRequest->requested_by,
                'delete_reason' => $deleteRequest->reason,
            ]);
            $location->delete();

            // Mark request approved
            $deleteRequest->update([
                'status'      => 'approved',
                'approved_at' => now(),
                'ip_address'  => $request->ip(),
                'user_agent'  => substr($request->userAgent() ?? '', 0, 500),
            ]);

            // Audit log
            AuditLog::create([
                'action'      => 'location.archived',
                'risk_level'  => 'high',
                'result'      => 'success',
                'actor_id'    => $deleteRequest->requested_by,
                'location_id' => null,
                'entity_type' => 'location',
                'entity_id'   => $location->id,
                'meta'        => [
                    'location_name'        => $location->name,
                    'reason'               => $deleteRequest->reason,
                    'move_to_location_id'  => $deleteRequest->move_to_location_id,
                    'affected_records'     => $deleteRequest->affected_records,
                    'request_id'           => $deleteRequest->id,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[LocationDelete] Approval failed', [
                'request_id' => $deleteRequest->id,
                'error'      => $e->getMessage(),
            ]);

            return $this->htmlPage(
                'Fout opgetreden',
                'Er is een fout opgetreden bij het archiveren van de locatie. Probeer het opnieuw of neem contact op met support.',
                '#C8102E',
                false
            );
        }

        $movedNote = $deleteRequest->move_to_location_id
            ? " Records zijn verplaatst naar locatie #{$deleteRequest->move_to_location_id}."
            : '';

        return $this->htmlPage(
            'Locatie gearchiveerd',
            "Locatie <strong>{$location->name}</strong> is succesvol gearchiveerd.{$movedNote}",
            '#16a34a',
            true
        );
    }

    /**
     * GET /locations/delete/cancel/{token}
     */
    public function cancel(string $token, Request $request): Response
    {
        $deleteRequest = LocationDeleteRequest::with('location')
            ->where('token', $token)
            ->first();

        if (! $deleteRequest) {
            return $this->htmlPage(
                'Ongeldig verzoek',
                'Dit verwijderingsverzoek bestaat niet.',
                '#C8102E',
                false
            );
        }

        if ($deleteRequest->status !== 'pending') {
            return $this->htmlPage(
                'Al verwerkt',
                "Dit verzoek heeft al de status: <strong>{$deleteRequest->status}</strong>.",
                '#64748b',
                false
            );
        }

        $deleteRequest->update(['status' => 'cancelled']);

        AuditLog::create([
            'action'      => 'location.delete_cancelled',
            'risk_level'  => 'medium',
            'result'      => 'success',
            'actor_id'    => null,
            'entity_type' => 'location',
            'entity_id'   => $deleteRequest->location_id,
            'meta'        => ['request_id' => $deleteRequest->id],
            'ip_address'  => $request->ip(),
        ]);

        return $this->htmlPage(
            'Verzoek geannuleerd',
            "Het verwijderingsverzoek voor locatie <strong>{$deleteRequest->location?->name}</strong> is geannuleerd.",
            '#2563eb',
            false
        );
    }

    // ── Private helpers ──────────────────────────────────────────

    private function moveRecords(int $fromId, int $toId): void
    {
        Yacht::where('ref_harbor_id', $fromId)->update(['ref_harbor_id' => $toId]);
        Boat::where('location_id', $fromId)->update(['location_id' => $toId]);
        Conversation::where('location_id', $fromId)->update(['location_id' => $toId]);
        Lead::where('location_id', $fromId)->update(['location_id' => $toId]);

        foreach (['tasks', 'boards', 'harbor_channels', 'sign_requests'] as $table) {
            try {
                DB::table($table)
                    ->where('location_id', $fromId)
                    ->update(['location_id' => $toId]);
            } catch (\Throwable) {
                // table may have different FK column — skip silently
            }
        }

        Log::info("[LocationDelete] Records moved from location {$fromId} to {$toId}");
    }

    private function htmlPage(string $title, string $body, string $color, bool $success): Response
    {
        $icon = $success ? '✓' : '⚠';
        $html = <<<HTML
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{$title} — Schepenkring</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 48px 40px; max-width: 520px; width: 100%; text-align: center; }
        .icon { font-size: 48px; color: {$color}; margin-bottom: 16px; }
        h1 { font-size: 24px; color: #0f172a; margin: 0 0 12px; }
        p { color: #475569; font-size: 15px; line-height: 1.6; margin: 0; }
        .badge { display: inline-block; margin-top: 24px; padding: 6px 18px; border-radius: 9999px; background: {$color}; color: #fff; font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">{$icon}</div>
        <h1>{$title}</h1>
        <p>{$body}</p>
        <div class="badge">Schepenkring Platform</div>
    </div>
</body>
</html>
HTML;

        return response($html, $success ? 200 : 410)->header('Content-Type', 'text/html');
    }
}
