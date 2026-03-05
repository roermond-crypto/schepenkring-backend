<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAuthorization;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\JsonResponse;

class AuthorizationController extends Controller 
{
    // Used by Sidebar to fetch current user rights
    public function getUserPermissions($userId): JsonResponse 
    {
        $permissions = UserAuthorization::where('user_id', $userId)
            ->pluck('operation_name');
            
        return response()->json($permissions);
    }

    // Used by Role Management Page to grant/revoke rights [cite: 28, 45]
public function syncAuthorizations(Request $request, $userId): JsonResponse 
{
    // Expecting an array of strings: ['manage yachts', 'manage tasks']
    $request->validate([
        'operations' => 'present|array',
        'operations.*' => 'string'
    ]);

    // 1. Wipe current permissions for this user to start fresh
    UserAuthorization::where('user_id', $userId)->delete();

    // 2. Insert the new selection
    $newAuths = [];
    foreach ($request->operations as $op) {
        $newAuths[] = [
            'user_id' => $userId,
            'operation_name' => $op,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    if (!empty($newAuths)) {
        UserAuthorization::insert($newAuths);
    }

    return response()->json(['message' => 'Permissions updated successfully']);
}
}