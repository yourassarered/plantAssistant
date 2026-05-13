<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ImageStorageService;
use App\Services\ModeratorAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvatarController extends Controller
{
    public function __construct(private readonly ModeratorAuditLogger $audit) {}

    public function show(int $userId)
    {
        $user = User::with('role')->findOrFail($userId);
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function update(UpdateAvatarRequest $request, ImageStorageService $images)
    {
        $user = $request->user();
        $this->authorize('updateProfile', $user);
        $avatar = $request->file('avatar');

        DB::transaction(function () use ($images, $user, $avatar): void {
            $images->delete($user->avatar_path);
            $user->avatar_path = $images->storeCompressed($avatar, 'avatars', 512, 84);
            $user->save();
        });

        return new UserResource($user->fresh('role'));
    }

    public function destroy(Request $request, ImageStorageService $images)
    {
        $user = $request->user();
        $this->authorize('updateProfile', $user);

        DB::transaction(function () use ($images, $user): void {
            $images->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        });

        return new UserResource($user->fresh('role'));
    }

    public function destroyForUser(Request $request, int $userId, ImageStorageService $images)
    {
        $this->authorize('manage', User::class);

        $user = User::findOrFail($userId);

        DB::transaction(function () use ($images, $user): void {
            $images->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        });

        $this->audit->log(
            actor: $request->user(),
            action: 'user.avatar_delete',
            targetType: User::class,
            targetId: $user->id,
            payload: null,
            request: $request
        );

        return new UserResource($user->fresh('role'));
    }
}
