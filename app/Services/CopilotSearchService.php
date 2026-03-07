<?php

namespace App\Services;

use App\Models\Boat;
use App\Models\Location;
use App\Models\User;

class CopilotSearchService
{
    public function __construct(
        private CopilotFuzzyMatcher $matcher,
        private LocationAccessService $locationAccess
    )
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
        return [];
    }

    private function searchBoats(string $query, User $user): array
    {
        $builder = Boat::query();
        if ($user->isClient()) {
            $builder->where('client_id', $user->id);
        } elseif ($user->isEmployee()) {
            $locationIds = $this->locationAccess->accessibleLocationIds($user);
            if (count($locationIds) === 0) {
                return [];
            }
            $builder->whereIn('location_id', $locationIds);
        }

        $idMatch = preg_match('/\d+/', $query, $matches) ? (int) $matches[0] : null;

        $builder->where(function ($q) use ($query, $idMatch) {
            $q->where('name', 'like', '%' . $query . '%')
                ->orWhere('status', 'like', '%' . $query . '%');
            if ($idMatch) {
                $q->orWhere('id', $idMatch);
            }
        });

        $items = $builder->limit(15)->get();

        return $this->mapResults('boat', $query, $items, function (Boat $boat) {
            return [
                'id' => $boat->id,
                'title' => $boat->name ?: 'Boat ' . $boat->id,
                'subtitle' => $boat->status,
            ];
        });
    }

    private function searchHarbors(string $query, User $user): array
    {
        $builder = Location::query();
        if ($user->isEmployee()) {
            $locationIds = $this->locationAccess->accessibleLocationIds($user);
            if (count($locationIds) === 0) {
                return [];
            }
            $builder->whereIn('id', $locationIds);
        } elseif ($user->isClient()) {
            if (! $user->client_location_id) {
                return [];
            }
            $builder->where('id', $user->client_location_id);
        }

        $builder->where(function ($q) use ($query) {
            $q->where('name', 'like', '%' . $query . '%')
                ->orWhere('code', 'like', '%' . $query . '%');
        });

        $items = $builder->limit(10)->get();

        return $this->mapResults('harbor', $query, $items, function (Location $location) {
            return [
                'id' => $location->id,
                'title' => $location->name,
                'subtitle' => $location->code,
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
        return [];
    }

    private function searchPayments(string $query, User $user): array
    {
        return [];
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
        return $user->isAdmin();
    }
}
