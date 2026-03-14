<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeUserController extends Controller
{
    public function index(Request $request, UserRepository $users)
    {
        $actor = $this->requireEmployee($request);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(array_map(fn (UserStatus $status) => $status->value, UserStatus::cases()))],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);
        $query = $users->queryClientsForUser($actor);
        $query = $users->queryWithFilters($validated, $query, false)
            ->orderByDesc('created_at');

        return UserResource::collection($query->paginate($perPage));
    }

    public function show(Request $request, int $id, UserRepository $users)
    {
        $actor = $this->requireEmployee($request);
        $user = $users->findClientForActorOrFail($actor, $id);

        return response()->json([
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ]);
    }

    private function requireEmployee(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthorized');
        }

        if (! $user->isEmployee()) {
            abort(403, 'Forbidden');
        }

        return $user;
    }
}
