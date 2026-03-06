<?php

namespace App\Actions\Signhost;

use App\Models\SignRequest;
use App\Models\User;
use App\Repositories\SignRequestRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ListSignhostDocumentsAction
{
    public function __construct(private SignRequestRepository $signRequests)
    {
    }

    public function execute(User $actor, array $data): Collection
    {
        $signRequest = $this->resolveRequest($actor, $data);

        if ($actor->isClient() && ! $this->clientCanAccess($signRequest, $actor)) {
            throw new AuthorizationException('Unauthorized');
        }

        return $signRequest->documents()->latest()->get();
    }

    private function resolveRequest(User $actor, array $data)
    {
        if (! empty($data['sign_request_id'])) {
            return $this->signRequests->findForUserOrFail($actor, (int) $data['sign_request_id']);
        }

        if (! empty($data['entity_type']) && ! empty($data['entity_id'])) {
            $signRequest = $this->signRequests->findLatestForEntity(
                $actor,
                $data['entity_type'],
                (int) $data['entity_id']
            );

            if ($signRequest) {
                return $signRequest;
            }
        }

        throw ValidationException::withMessages([
            'sign_request' => 'Sign request not found.',
        ]);
    }

    private function clientCanAccess(SignRequest $signRequest, User $actor): bool
    {
        if ($signRequest->requested_by_user_id === $actor->id) {
            return true;
        }

        $recipients = $signRequest->metadata['recipients'] ?? [];
        foreach ($recipients as $recipient) {
            if (! empty($recipient['user_id']) && (int) $recipient['user_id'] === $actor->id) {
                return true;
            }
        }

        return false;
    }
}
