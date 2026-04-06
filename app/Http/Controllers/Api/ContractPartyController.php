<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContractParty;
use App\Models\User;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ContractPartyController extends Controller
{
    public function __construct(private LocationAccessService $locations)
    {
    }

    public function index(Request $request)
    {
        $user = $this->requireStaff($request);

        $validated = $request->validate([
            'role_type' => ['required', 'string', 'in:seller,buyer'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        $query = ContractParty::query()
            ->where('role_type', $validated['role_type'])
            ->orderBy('name')
            ->orderBy('id');

        $query = $this->scopeQuery($query, $user);

        if (array_key_exists('location_id', $validated) && $validated['location_id'] !== null) {
            $locationId = (int) $validated['location_id'];
            $this->authorizeLocation($user, $locationId);
            $query->where('location_id', $locationId);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->requireStaff($request);
        $validated = $this->validatePayload($request);

        $this->authorizeOptionalLocation($user, $validated['location_id'] ?? null);

        $party = ContractParty::create($validated);

        return response()->json($party, 201);
    }

    public function update(Request $request, ContractParty $contractParty)
    {
        $user = $this->requireStaff($request);
        $this->authorizeParty($user, $contractParty);

        $validated = $this->validatePayload($request, true);

        if (array_key_exists('location_id', $validated)) {
            $this->authorizeOptionalLocation($user, $validated['location_id']);
        }

        $contractParty->fill($validated);
        $contractParty->save();

        return response()->json($contractParty->fresh());
    }

    public function destroy(Request $request, ContractParty $contractParty)
    {
        $user = $this->requireStaff($request);
        $this->authorizeParty($user, $contractParty);

        $contractParty->delete();

        return response()->json([
            'message' => 'deleted',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'role_type' => array_filter([
                $partial ? 'sometimes' : 'required',
                'string',
                'in:seller,buyer',
            ]),
            'name' => array_filter([
                $partial ? 'sometimes' : 'required',
                'string',
                'max:255',
            ]),
            'company_name' => array_filter([
                $partial ? 'sometimes' : null,
                'nullable',
                'string',
                'max:255',
            ]),
            'address' => array_filter([
                $partial ? 'sometimes' : null,
                'nullable',
                'string',
                'max:255',
            ]),
            'postal_code' => array_filter([
                $partial ? 'sometimes' : null,
                'nullable',
                'string',
                'max:50',
            ]),
            'city' => array_filter([
                $partial ? 'sometimes' : null,
                'nullable',
                'string',
                'max:120',
            ]),
            'phone' => array_filter([
                $partial ? 'sometimes' : null,
                'nullable',
                'string',
                'max:80',
            ]),
            'email' => array_filter([
                $partial ? 'sometimes' : null,
                'nullable',
                'email',
                'max:255',
            ]),
            'passport_number' => array_filter([
                $partial ? 'sometimes' : null,
                'nullable',
                'string',
                'max:120',
            ]),
            'partner_name' => array_filter([
                $partial ? 'sometimes' : null,
                'nullable',
                'string',
                'max:255',
            ]),
            'married' => array_filter([
                $partial ? 'sometimes' : null,
                'boolean',
            ]),
            'location_id' => array_filter([
                $partial ? 'sometimes' : null,
                'nullable',
                'integer',
                'exists:locations,id',
            ]),
        ]);
    }

    private function requireStaff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isStaff(), 403, 'Forbidden');

        return $user;
    }

    private function authorizeLocation(User $user, int $locationId): void
    {
        if (! $this->locations->sharesLocation($user, $locationId)) {
            abort(403, 'Forbidden');
        }
    }

    private function authorizeOptionalLocation(User $user, mixed $locationId): void
    {
        if ($locationId === null || $locationId === '') {
            return;
        }

        $this->authorizeLocation($user, (int) $locationId);
    }

    private function authorizeParty(User $user, ContractParty $contractParty): void
    {
        $this->authorizeOptionalLocation($user, $contractParty->location_id);
    }

    private function scopeQuery(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $locationIds = $this->locations->accessibleLocationIds($user);

        return $query->where(function (Builder $builder) use ($locationIds) {
            $builder->whereNull('location_id');

            if ($locationIds !== []) {
                $builder->orWhereIn('location_id', $locationIds);
            }
        });
    }
}
