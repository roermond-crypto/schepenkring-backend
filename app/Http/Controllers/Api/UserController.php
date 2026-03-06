<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $query = User::query()->orderByDesc('created_at');

        if ($request->filled('role')) {
            $query->byRole($request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $query->paginate($request->input('per_page', 25));
    }

    /**
     * Show a single user.
     */
    public function show(int $id)
    {
        return User::with(['invitedBy:id,name,email'])->findOrFail($id);
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
            'role'  => ['required', Rule::in(['employee', 'client'])],
            'phone' => 'nullable|string|max:50',
        ]);

        $tempPassword = Str::random(12);

        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'role'       => $validated['role'],
            'phone'      => $validated['phone'] ?? null,
            'password'   => Hash::make($tempPassword),
            'invited_by' => $request->user()->id,
            'is_active'  => true,
        ]);

        // TODO: Send invitation email with temp password or password reset link

        return response()->json([
            'user'           => $user,
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
            'role'      => ['sometimes', Rule::in(['admin', 'employee', 'client'])],
            'phone'     => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Deactivate a user (soft-disable, not delete).
     */
    public function destroy(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => false]);

        return response()->json(['message' => 'User deactivated.']);
    }
}
