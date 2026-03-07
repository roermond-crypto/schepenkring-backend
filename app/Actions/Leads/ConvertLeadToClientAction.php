<?php

namespace App\Actions\Leads;

use App\Actions\User\CreateUserAction;
use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActionSecurity;
use App\Services\NotificationDispatchService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConvertLeadToClientAction
{
    public function __construct(
        private CreateUserAction $createUser,
        private ActionSecurity $security,
        private NotificationDispatchService $notifications
    ) {
    }

    public function execute(User $actor, Lead $lead, ?string $idempotencyKey = null): User
    {
        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        if ($lead->converted_client_id) {
            return $lead->convertedClient ?? User::findOrFail($lead->converted_client_id);
        }

        if (! $lead->name || ! $lead->email) {
            throw ValidationException::withMessages([
                'lead' => 'Lead must have name and email to convert.',
            ]);
        }

        $existing = User::where('email', $lead->email)->first();
        if ($existing) {
            if ($existing->isClient()) {
                $before = $lead->toArray();
                $lead->update([
                    'converted_client_id' => $existing->id,
                    'status' => 'converted',
                ]);

                $this->security->log('lead.converted', RiskLevel::HIGH, $actor, $lead, [
                    'client_id' => $existing->id,
                ], [
                    'location_id' => $lead->location_id,
                    'snapshot_before' => $before,
                    'snapshot_after' => $lead->toArray(),
                    'idempotency_key' => $idempotencyKey,
                ]);

                return $existing;
            }

            throw ValidationException::withMessages([
                'email' => 'Email already belongs to another user.',
            ]);
        }

        $password = Str::random(20);

        $client = $this->createUser->execute([
            'type' => UserType::CLIENT->value,
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'address_line1' => $lead->address_line1,
            'address_line2' => $lead->address_line2,
            'city' => $lead->city,
            'state' => $lead->state,
            'postal_code' => $lead->postal_code,
            'country' => $lead->country,
            'password' => $password,
            'status' => UserStatus::ACTIVE->value,
            'location_id' => $lead->location_id,
        ], $actor, $idempotencyKey);

        $before = $lead->toArray();

        $lead->update([
            'converted_client_id' => $client->id,
            'status' => 'converted',
        ]);

        $this->security->log('lead.converted', RiskLevel::HIGH, $actor, $lead, [
            'client_id' => $client->id,
        ], [
            'location_id' => $lead->location_id,
            'snapshot_before' => $before,
            'snapshot_after' => $lead->toArray(),
            'idempotency_key' => $idempotencyKey,
        ]);

        if ($lead->assignedEmployee) {
            $this->notifications->notifyUser(
                $lead->assignedEmployee,
                'success',
                'Lead converted',
                'A lead was converted to a client.',
                [
                    'lead_id' => $lead->id,
                    'client_id' => $client->id,
                ],
                null,
                true,
                true,
                $lead->location_id
            );
        }

        return $client;
    }
}
