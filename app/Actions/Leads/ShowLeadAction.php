<?php

namespace App\Actions\Leads;

use App\Models\Lead;
use App\Models\User;
use App\Repositories\LeadRepository;
use Illuminate\Auth\Access\AuthorizationException;

class ShowLeadAction
{
    public function __construct(private LeadRepository $leads)
    {
    }

    public function execute(User $actor, int $id): Lead
    {
        if ($actor->isClient()) {
            throw new AuthorizationException('Unauthorized');
        }

        $lead = $this->leads->findForUserOrFail($id, $actor);

        return $lead->load(['conversation', 'assignedEmployee', 'convertedClient', 'location']);
    }
}
