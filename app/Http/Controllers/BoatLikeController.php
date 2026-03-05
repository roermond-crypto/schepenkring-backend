<?php

namespace App\Http\Controllers;

use App\Models\BoatLike;
use App\Models\Yacht;
use Illuminate\Http\Request;

class BoatLikeController extends Controller
{
    public function like(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $yacht = Yacht::findOrFail($id);

        $like = BoatLike::firstOrCreate([
            'user_id' => $user->id,
            'yacht_id' => $yacht->id,
        ]);

        return response()->json([
            'liked' => true,
            'yacht' => $yacht,
        ], $like->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $yachts = $user->likedYachts()->orderByDesc('boat_likes.created_at')->get();

        return response()->json([
            'data' => $yachts,
        ]);
    }
}
