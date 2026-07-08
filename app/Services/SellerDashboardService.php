<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SellerDashboardService
{
    public function summary(User $user): array
    {
        return Cache::remember("seller_dashboard_v2_{$user->id}", 300, function () use ($user) {
            $yachtIds = Yacht::where('seller_id', $user->id)
                ->orWhere('user_id', $user->id)
                ->pluck('id');

            $stats = $this->buildStats($yachtIds);
            $actionNeeded = $this->buildActionNeeded($yachtIds);
            $boats = $this->buildBoatsList($yachtIds);
            $latestBids = $this->buildLatestBids($yachtIds);

            return [
                'stats'        => $stats,
                'action_needed' => $actionNeeded,
                'boats'        => $boats,
                'latest_bids'  => $latestBids,
            ];
        });
    }

    private function buildStats(Collection $yachtIds): array
    {
        $boatCount = $yachtIds->count();

        $openBids = Offer::whereIn('yacht_id', $yachtIds)
            ->whereIn('status', ['new', 'sent_to_seller'])
            ->count();

        $openConversations = Conversation::whereIn('boat_id', $yachtIds)
            ->where('status', '!=', 'closed')
            ->count();

        // Viewings are conversations of type plan_viewing
        $pendingViewings = Conversation::whereIn('boat_id', $yachtIds)
            ->where('chat_type', 'plan_viewing')
            ->where('status', '!=', 'closed')
            ->count();

        // Questions are conversations of type question
        $openQuestions = Conversation::whereIn('boat_id', $yachtIds)
            ->where('chat_type', 'question')
            ->where('status', '!=', 'closed')
            ->count();

        return [
            'boat_count'         => $boatCount,
            'open_bids'          => $openBids,
            'open_conversations' => $openConversations,
            'pending_viewings'   => $pendingViewings,
            'open_questions'     => $openQuestions,
            'contracts'          => 0,
        ];
    }

    private function buildActionNeeded(Collection $yachtIds): array
    {
        $items = [];

        $newBids = Offer::whereIn('yacht_id', $yachtIds)
            ->where('status', 'new')
            ->count();

        if ($newBids > 0) {
            $items[] = [
                'type'  => 'new_bids',
                'label' => "{$newBids} nieuw bod" . ($newBids !== 1 ? 'en' : '') . " wacht op reactie",
                'count' => $newBids,
                'href'  => '/bids',
            ];
        }

        $unansweredQuestions = Conversation::whereIn('boat_id', $yachtIds)
            ->where('chat_type', 'question')
            ->where('status', 'open')
            ->whereNull('last_staff_message_at')
            ->count();

        if ($unansweredQuestions > 0) {
            $items[] = [
                'type'  => 'unanswered_questions',
                'label' => "{$unansweredQuestions} onbeantwoorde vraag" . ($unansweredQuestions !== 1 ? 'en' : ''),
                'count' => $unansweredQuestions,
                'href'  => '/chat',
            ];
        }

        return $items;
    }

    private function buildBoatsList(Collection $yachtIds): array
    {
        return Yacht::whereIn('id', $yachtIds)
            ->select('id', 'boat_name', 'status', 'price')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($yacht) {
                $newBidsCount = Offer::where('yacht_id', $yacht->id)
                    ->where('status', 'new')
                    ->count();

                return [
                    'id'            => $yacht->id,
                    'boat_name'     => $yacht->boat_name,
                    'status'        => $yacht->status,
                    'price'         => $yacht->price,
                    'new_bids_count' => $newBidsCount,
                ];
            })
            ->toArray();
    }

    private function buildLatestBids(Collection $yachtIds): array
    {
        return Offer::with('yacht:id,boat_name')
            ->whereIn('yacht_id', $yachtIds)
            ->whereIn('status', ['new', 'sent_to_seller', 'seller_countered'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($offer) {
                return [
                    'id'         => $offer->id,
                    'yacht_id'   => $offer->yacht_id,
                    'boat_name'  => $offer->yacht?->boat_name,
                    'buyer_name' => $offer->buyer_name,
                    'amount'     => $offer->amount,
                    'status'     => $offer->status,
                    'chat_url'   => $offer->conversation_id ? "/chat?conversation={$offer->conversation_id}" : null,
                ];
            })
            ->toArray();
    }
}
