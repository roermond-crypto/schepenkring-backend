<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Validator; 

class PartnerUserController extends Controller
{
    /**
     * Get all users owned by the authenticated partner.
     */
    public function index(Request $request)
    {
        $actor = auth()->user();
        $partnerId = $this->resolvePartnerId($actor);

        if (!$partnerId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::where('partner_id', $partnerId);

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('name')->get();

        // Remove this if relationship doesn't exist:
        // $users->load('pagePermissions.page');

        return response()->json($users);
    }

    /**
     * Create a new user under this partner.
     */
    public function store(Request $request)
    {
        $partner = auth()->user();

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:8',
            'role'         => 'required|in:Employee,Customer,Seller',
            'status'       => 'required|in:Active,Suspended',
            'access_level' => 'required|in:Full,Limited,None',
        ]);

        $user = User::create([
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'password'     => Hash::make($validated['password']),
            'role'         => $validated['role'],
            'status'       => $validated['status'],
            'access_level' => $validated['access_level'],
            'partner_id'   => $partner->id,
        ]);

        // Assign Spatie role
        $role = Role::findByName($validated['role'], 'web');
        $user->assignRole($role);

        return response()->json($user, 201);
    }

    /**
     * Show a single user (only if owned by this partner).
     */
    public function show($id)
    {
        $actor = auth()->user();
        $partnerId = $this->resolvePartnerId($actor);

        if (!$partnerId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::where('partner_id', $partnerId)->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Update a user owned by this partner.
     */
    public function update(Request $request, $id)
    {
        $partner = auth()->user();
        if ($partner->role !== 'Partner') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $user = User::where('partner_id', $partner->id)->findOrFail($id);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'email'        => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password'     => 'sometimes|min:8',
            'role'         => 'sometimes|in:Employee,Customer,Seller',
            'status'       => 'sometimes|in:Active,Suspended',
            'access_level' => 'sometimes|in:Full,Limited,None',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        // If role changed, update Spatie role
        if (isset($validated['role']) && $validated['role'] !== $user->getOriginal('role')) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json($user);
    }

    /**
     * Delete a user owned by this partner.
     */
    public function destroy($id)
    {
        $partner = auth()->user();
        if ($partner->role !== 'Partner') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $user = User::where('partner_id', $partner->id)->findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }

    private function resolvePartnerId($actor): ?int
    {
        if (!$actor) {
            return null;
        }

        if ($actor->role === 'Partner') {
            return $actor->id;
        }

        if ($actor->role === 'Employee' && $actor->partner_id) {
            return (int) $actor->partner_id;
        }

        return null;
    }

    /**
     * Public registration for sellers via partner token.
     */
    public function registerSeller(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:8',
            'token'      => 'required|string|exists:users,partner_token',
        ]);

        // Find the partner by token
        $partner = User::where('partner_token', $validated['token'])
                       ->where('role', 'Partner')
                       ->firstOrFail();

        $user = User::create([
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'password'     => Hash::make($validated['password']),
            'role'         => 'Seller',
            'status'       => 'Active',
            'access_level' => 'Limited',
            'partner_id'   => $partner->id,
        ]);

        // Assign Spatie role
        $role = Role::findByName('Seller', 'web');
        $user->assignRole($role);

        // Return a token so the seller can log in immediately
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }
}
