<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Str;

class AvatarController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // Max 5MB
        ]);

        $user = $request->user();
        $file = $request->file('avatar');

        // Delete old avatar if exists
        if ($user->avatar) {
            $oldPath = str_replace('/storage/', '', parse_url($user->avatar, PHP_URL_PATH));
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Generate filename
        $filename = 'avatars/' . $user->id . '_' . Str::random(10) . '.webp';

        // Read image from file, crop/resize to 400x400, and encode as WEBP string
        $image = Image::read($file);
        
        // Use cover method to resize and crop to a square, ensuring the face/center is maintained 
        $encoded = $image->cover(400, 400)->toWebp(80);

        // Save to public disk
        Storage::disk('public')->put($filename, (string) $encoded);

        // Update User avatar url
        $user->forceFill([
            'avatar' => config('app.url') . '/storage/' . $filename,
        ])->save();

        return response()->json([
            'message' => 'Avatar updated successfully',
            'data' => new UserResource($user->load(['locations', 'clientLocation'])),
        ]);
    }
}
