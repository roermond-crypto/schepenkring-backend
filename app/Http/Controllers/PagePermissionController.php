<?php

namespace App\Http\Controllers;

use App\Models\PagePermission;
use App\Models\UserPagePermission;
use App\Models\User;
use Illuminate\Http\Request;

class PagePermissionController extends Controller
{

    // Add this method at the top
private function authorizeUserAccess($userId)
{
    $currentUser = auth()->user();
    $targetUser = User::findOrFail($userId);

    // Admin can access any user
    if ($currentUser->role === 'Admin') {
        return;
    }

    // Partner can only access users with partner_id == their own id
    if ($currentUser->role === 'Partner' && $targetUser->partner_id === $currentUser->id) {
        return;
    }

    abort(403, 'Unauthorized access to user permissions.');
}

    // Get all page permissions (for dropdown/listing)
    public function index()
    {
        $pages = PagePermission::all();
        return response()->json($pages);
    }

    // Get user's specific permissions
    public function getUserPermissions($userId)
    {

        
    $this->authorizeUserAccess($userId); 
        $permissions = UserPagePermission::where('user_id', $userId)
            ->with('page')
            ->get()
            ->map(function($permission) {
                return [
                    'page_key' => $permission->page->page_key,
                    'page_name' => $permission->page->page_name,
                    'permission_value' => $permission->permission_value
                ];
            });
        
        return response()->json($permissions);
    }

    // Update user's page permission
    public function updatePermission(Request $request, $userId)
    {
        $request->validate([
            'page_key' => 'required|string',
            'permission_value' => 'required|integer|in:0,1,2'
        ]);

        $page = PagePermission::where('page_key', $request->page_key)->first();
        
        if (!$page) {
            return response()->json(['error' => 'Page not found'], 404);
        }

        $userPermission = UserPagePermission::updateOrCreate(
            [
                'user_id' => $userId,
                'page_permission_id' => $page->id
            ],
            [
                'permission_value' => $request->permission_value
            ]
        );

        return response()->json([
            'message' => 'Permission updated successfully',
            'permission' => $userPermission
        ]);
    }

    // Reset all permissions for a user to 0
    public function resetPermissions($userId)
    {
        UserPagePermission::where('user_id', $userId)->update(['permission_value' => 0]);
        
        return response()->json([
            'message' => 'All permissions reset to default'
        ]);
    }

    // Bulk update permissions
    public function bulkUpdate(Request $request, $userId)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*.page_key' => 'required|string',
            'permissions.*.permission_value' => 'required|integer|in:0,1,2'
        ]);

        foreach ($request->permissions as $permission) {
            $page = PagePermission::where('page_key', $permission['page_key'])->first();
            
            if ($page) {
                UserPagePermission::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'page_permission_id' => $page->id
                    ],
                    [
                        'permission_value' => $permission['permission_value']
                    ]
                );
            }
        }

        return response()->json(['message' => 'Permissions updated successfully']);
    }
}