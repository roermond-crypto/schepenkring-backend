<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ChatAccessService
{
    public function __construct(private LocationAccessService $locations)
    {
    }

    public function scopeConversations(Builder $query, User $user, bool $assignedOnly = false): Builder
    {
        $query = $this->locations->scopeQuery($query, $user, 'location_id');

        if ($assignedOnly) {
            $query->where(function (Builder $sub) use ($user) {
                $sub->where('assigned_to', $user->id)
                    ->orWhere('assigned_employee_id', $user->id);
            });
        }

        return $query;
    }

    public function canAccessConversation(User $user, Conversation $conversation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Client users may only access conversations that belong to them
        // (matched by user_id or by their email on the linked contact).
        if ($user->isClient()) {
            if ($conversation->user_id === $user->id) {
                return true;
            }
            $conversation->loadMissing('contact');
            if ($conversation->contact && $conversation->contact->email === $user->email) {
                return true;
            }
            return false;
        }

        return $this->locations->sharesLocation($user, $conversation->location_id);
    }

    public function canAccessLocation(User $user, ?int $locationId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->locations->sharesLocation($user, $locationId);
    }
}
