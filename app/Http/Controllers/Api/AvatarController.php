<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    public function show(Request $request, int $userId)
    {
        return new UserResource(User::with('role')->findOrFail($userId));
    }

    public function update(Request $request, ImageStorageService $images)
    {
        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user = $request->user();
        $images->delete($user->avatar_path);

        $user->avatar_path = $images->storeCompressed($validated['avatar'], 'avatars', 512, 84);
        $user->save();

        return new UserResource($user->load('role'));
    }

    public function destroy(Request $request, ImageStorageService $images)
    {
        $user = $request->user();
        $images->delete($user->avatar_path);

        $user->avatar_path = null;
        $user->save();

        return new UserResource($user->load('role'));
    }

    public function destroyForUser(int $userId, ImageStorageService $images)
    {
        $user = User::findOrFail($userId);
        $images->delete($user->avatar_path);

        $user->avatar_path = null;
        $user->save();

        return new UserResource($user->load('role'));
    }
}
