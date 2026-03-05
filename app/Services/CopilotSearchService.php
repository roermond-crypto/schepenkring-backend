<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\InvoiceDocument;
use App\Models\Payment;
use App\Models\User;
use App\Models\Yacht;

class CopilotSearchService
{
    public function __construct(private CopilotFuzzyMatcher $matcher)
    {
    }

    public function search(string $query, User $user, int $limit = 8): array
    {
        $normalized = $this->matcher->normalize($query);
        if ($normalized === '') {
            return [];
        }

        $results = [];

        $results = array_merge($results, $this->searchInvoices($query, $user));
        $results = array_merge($results, $this->searchBoats($query, $user));
        $results = array_merge($results, $this->searchHarbors($query, $user));
        $results = array_merge($results, $this->searchUsers($query, $user));
        $results = array_merge($results, $this->searchDeals($query, $user));
        $results = array_merge($results, $this->searchPayments($query, $user));

        usort($results, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($results, 0, $limit);
    }

    private function searchInvoices(string $query, User $user): array
    {
        if (!$this->isAdmin($user)) {
            return [];
        }

        $idMatch = preg_match('/\d+/', $query, $matches) ? (int) $matches[0] : null;
        $builder = InvoiceDocument::query();
        if ($idMatch) {
            $builder->where('id', $idMatch);
        } else {
            $builder->where('source_filename', 'like', '%' . $query . '%');
        }

        $items = $builder->limit(10)->get();
        return $this->mapResults('invoice', $query, $items, function (InvoiceDocument $doc) {
            return [
                'id' => $doc->id,
                'title' => "Invoice #{$doc->id}",
                'subtitle' => $doc->source_filename,
            ];
        });
    }

    private function searchBoats(string $query, User $user): array
    {
        $builder = Yacht::query();
        if (!$this->isAdmin($user)) {
            $builder->where('user_id', $user->id);
        }

        $builder->where(function ($q) use ($query) {
            $q->where('boat_name', 'like', '%' . $query . '%')
                ->orWhere('manufacturer', 'like', '%' . $query . '%')
                ->orWhere('model', 'like', '%' . $query . '%')
                ->orWhere('vessel_id', 'like', '%' . $query . '%');
        });

        $items = $builder->limit(15)->get();

        return $this->mapResults('boat', $query, $items, function (Yacht $boat) {
            return [
                'id' => $boat->id,
                'title' => $boat->boat_name ?: 'Boat ' . $boat->id,
                'subtitle' => $boat->vessel_id,
            ];
        });
    }

    private function searchHarbors(string $query, User $user): array
    {
        $builder = User::query()->where('role', 'Partner');
        if (!$this->isAdmin($user)) {
            $builder->where('id', $user->id);
        }

        $builder->where(function ($q) use ($query) {
            $q->where('name', 'like', '%' . $query . '%')
                ->orWhere('email', 'like', '%' . $query . '%');
        });

        $items = $builder->limit(10)->get();

        return $this->mapResults('harbor', $query, $items, function (User $harbor) {
            return [
                'id' => $harbor->id,
                'title' => $harbor->name,
                'subtitle' => $harbor->email,
            ];
        });
    }

    private function searchUsers(string $query, User $user): array
    {
        if (!$this->isAdmin($user)) {
            return [];
        }

        $items = User::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%' . $query . '%')
                    ->orWhere('email', 'like', '%' . $query . '%');
            })
            ->limit(10)
            ->get();

        return $this->mapResults('user', $query, $items, function (User $candidate) {
            return [
                'id' => $candidate->id,
                'title' => $candidate->name,
                'subtitle' => $candidate->email,
            ];
        });
    }

    private function searchDeals(string $query, User $user): array
    {
        $builder = Deal::query();
        if (!$this->isAdmin($user)) {
            $builder->where('seller_user_id', $user->id)->orWhere('buyer_user_id', $user->id);
        }

        $idMatch = preg_match('/\d+/', $query, $matches) ? (int) $matches[0] : null;
        if ($idMatch) {
            $builder->where('id', $idMatch);
        }

        $items = $builder->limit(8)->get();

        return $this->mapResults('deal', $query, $items, function (Deal $deal) {
            return [
                'id' => $deal->id,
                'title' => "Deal #{$deal->id}",
                'subtitle' => $deal->status,
            ];
        });
    }

    private function searchPayments(string $query, User $user): array
    {
        if (!$this->isAdmin($user)) {
            return [];
        }

        $builder = Payment::query();
        $idMatch = preg_match('/\d+/', $query, $matches) ? (int) $matches[0] : null;
        if ($idMatch) {
            $builder->where('id', $idMatch);
        } else {
            $builder->where('mollie_payment_id', 'like', '%' . $query . '%');
        }

        $items = $builder->limit(10)->get();

        return $this->mapResults('payment', $query, $items, function (Payment $payment) {
            return [
                'id' => $payment->id,
                'title' => "Payment #{$payment->id}",
                'subtitle' => $payment->mollie_payment_id ?: $payment->status,
            ];
        });
    }

    private function mapResults(string $type, string $query, $items, callable $formatter): array
    {
        $results = [];
        foreach ($items as $item) {
            $data = $formatter($item);
            $title = $data['title'] ?? '';
            $subtitle = $data['subtitle'] ?? '';
            $score = max(
                $this->matcher->score($query, $title),
                $subtitle ? $this->matcher->score($query, $subtitle) : 0.0
            );

            $results[] = [
                'type' => $type,
                'id' => $data['id'],
                'title' => $title,
                'subtitle' => $subtitle,
                'score' => round($score, 3),
            ];
        }

        return $results;
    }

    private function isAdmin(User $user): bool
    {
        $role = strtolower((string) $user->role);
        return $role === 'admin' || $role === 'superadmin';
    }
}
