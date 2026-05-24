<?php

namespace App\Services;

use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SellerDashboardService
{
    public function summary(User $user): array
    {
        return Cache::remember("seller_dashboard_{$user->id}", 300, function () use ($user) {
            return [
                'listings' => $this->listingsSummary($user),
                'bids' => $this->bidsSummary($user),
                'conversations' => $this->conversationsSummary($user),
                'tasks' => $this->tasksSummary($user),
                'revenue' => $this->revenueSummary($user),
                'recent_bids' => $this->recentBids($user),
            ];
        });
    }

    private function listingsSummary(User $user): array
    {
        $counts = Yacht::where('user_id', $user->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'active' => (int) ($counts['published'] ?? $counts['active'] ?? 0),
            'draft' => (int) ($counts['draft'] ?? 0),
            'sold' => (int) ($counts['sold'] ?? 0),
        ];
    }

    private function bidsSummary(User $user): array
    {
        $yachtIds = Yacht::where('user_id', $user->id)->pluck('id');

        $counts = Bid::whereIn('yacht_id', $yachtIds)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total_received' => (int) $counts->sum(),
            'pending_review' => (int) ($counts['pending'] ?? 0),
            'accepted' => (int) ($counts['accepted'] ?? 0),
        ];
    }

    private function conversationsSummary(User $user): array
    {
        if (!class_exists(\App\Models\ChatConversation::class)) {
            return ['open' => 0, 'unread' => 0];
        }

        $open = \App\Models\ChatConversation::where('user_id', $user->id)
            ->where('status', '!=', 'closed')
            ->count();

        $unread = \App\Models\ChatConversation::where('user_id', $user->id)
            ->where('has_unread', true)
            ->count();

        return ['open' => $open, 'unread' => $unread];
    }

    private function tasksSummary(User $user): array
    {
        $pending = Task::where('assigned_to', $user->id)
            ->where('status', 'pending')
            ->count();

        $overdue = Task::where('assigned_to', $user->id)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        return ['pending' => $pending, 'overdue' => $overdue];
    }

    private function revenueSummary(User $user): array
    {
        $yachtIds = Yacht::where('user_id', $user->id)->pluck('id');

        if (!class_exists(\App\Models\YachtFinancialLog::class)) {
            return ['total_eur' => 0, 'this_month_eur' => 0];
        }

        $total = \App\Models\YachtFinancialLog::whereIn('yacht_id', $yachtIds)
            ->where('type', 'sale')
            ->sum('amount_eur');

        $thisMonth = \App\Models\YachtFinancialLog::whereIn('yacht_id', $yachtIds)
            ->where('type', 'sale')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount_eur');

        return [
            'total_eur' => (int) $total,
            'this_month_eur' => (int) $thisMonth,
        ];
    }

    private function recentBids(User $user): array
    {
        $yachtIds = Yacht::where('user_id', $user->id)->pluck('id');

        return Bid::with(['yacht:id,name', 'user:id,name'])
            ->whereIn('yacht_id', $yachtIds)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($bid) => [
                'id' => $bid->id,
                'yacht_name' => $bid->yacht?->name ?? 'Unknown',
                'amount' => $bid->amount,
                'bidder' => $bid->user?->name ?? 'Anonymous',
                'status' => $bid->status,
                'created_at' => $bid->created_at?->toISOString(),
            ])
            ->toArray();
    }
}
