<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ChatAccessService
{
    public function scopeConversations(Builder $query, User $user, bool $assignedOnly = false): Builder
    {
        if ($this->isPlatformAdmin($user)) {
            return $query;
        }

        if ($this->isHarborAdmin($user)) {
            return $query->where('harbor_id', $user->id);
        }

        if (!$this->isStaff($user)) {
            return $query->whereHas('contact', function (Builder $contactQuery) use ($user) {
                $contactQuery->where('email', $user->email);
            });
        }

        $harborId = $user->partner_id;
        if ($harborId) {
            $query->where('harbor_id', $harborId);
        }

        if ($assignedOnly || !$harborId) {
            $query->where(function (Builder $sub) use ($user) {
                $sub->where('assigned_to', $user->id)
                    ->orWhereHas('participants', function (Builder $participantQuery) use ($user) {
                        $participantQuery->where('user_id', $user->id);
                    });
            });
        }

        return $query;
    }

    public function canAccessConversation(User $user, Conversation $conversation): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        if ($this->isHarborAdmin($user)) {
            return (int) $conversation->harbor_id === (int) $user->id;
        }

        if (!$this->isStaff($user)) {
            return $conversation->contact && $conversation->contact->email === $user->email;
        }

        $harborId = $user->partner_id;
        if ($harborId && (int) $conversation->harbor_id === (int) $harborId) {
            return true;
        }

        if ((int) $conversation->assigned_to === (int) $user->id) {
            return true;
        }

        return ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function isPlatformAdmin(User $user): bool
    {
        return strtolower((string) $user->role) === 'admin';
    }

    private function isHarborAdmin(User $user): bool
    {
        return strtolower((string) $user->role) === 'partner';
    }

    private function isStaff(User $user): bool
    {
        $role = strtolower((string) $user->role);
        return in_array($role, ['admin', 'partner', 'employee'], true);
    }
}
