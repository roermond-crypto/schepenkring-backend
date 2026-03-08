<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List all users (admin only).
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->with(['locations', 'clientLocation'])
            ->orderByDesc('created_at');

        if ($request->filled('role')) {
            $query->byRole((string) $request->string('role'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('status', $request->boolean('is_active') ? 'ACTIVE' : 'DISABLED');
        }

        return UserResource::collection($query->paginate($request->input('per_page', 25)));
    }

    /**
     * Show a single user.
     */
    public function show(int $id)
    {
        $user = User::with(['invitedBy:id,name,email', 'locations', 'clientLocation'])->findOrFail($id);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Create/invite a new employee or partner (admin only).
     * Clients can self-register via the auth flow.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role'  => ['required_without:type', Rule::in(['admin', 'employee', 'client', 'ADMIN', 'EMPLOYEE', 'CLIENT'])],
            'type'  => ['required_without:role', Rule::in(['ADMIN', 'EMPLOYEE', 'CLIENT', 'admin', 'employee', 'client'])],
            'phone' => 'nullable|string|max:50',
        ]);

        $tempPassword = Str::random(12);
        $role = $validated['role'] ?? $validated['type'];

        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'role'       => $role,
            'phone'      => $validated['phone'] ?? null,
            'password'   => Hash::make($tempPassword),
            'invited_by' => $request->user()->id,
            'status'     => 'ACTIVE',
        ]);

        $user->load(['locations', 'clientLocation']);
        $resource = new UserResource($user);

        // TODO: Send invitation email with temp password or password reset link

        return response()->json([
            'user'           => $resource,
            'data'           => $resource,
            'temp_password'  => $tempPassword, // Only return in response, never log
            'message'        => 'User created. Share the temporary password securely.',
        ], 201);
    }

    /**
     * Update a user (admin only).
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'role'      => ['sometimes', Rule::in(['admin', 'employee', 'client', 'ADMIN', 'EMPLOYEE', 'CLIENT'])],
            'type'      => ['sometimes', Rule::in(['ADMIN', 'EMPLOYEE', 'CLIENT', 'admin', 'employee', 'client'])],
            'phone'     => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        if (array_key_exists('type', $validated) && ! array_key_exists('role', $validated)) {
            $validated['role'] = $validated['type'];
        }
        unset($validated['type']);

        if (array_key_exists('is_active', $validated)) {
            $validated['status'] = $validated['is_active'] ? 'ACTIVE' : 'DISABLED';
            unset($validated['is_active']);
        }

        $user->update($validated);
        $user->load(['locations', 'clientLocation']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Deactivate a user (soft-disable, not delete).
     */
    public function destroy(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'DISABLED']);

        return response()->json([
            'message' => 'User deactivated.',
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ]);
    }
}
