<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function follow(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('follow', [Follow::class, $targetUser]);

        $follow = Follow::firstOrCreate([
            'follower_id' => $request->user()->id,
            'following_id' => $userId,
        ]);

        if (! $follow->wasRecentlyCreated) {
            return response()->json([
                'message' => 'You are already following this user',
            ], 422);
        }

        return response()->json([
            'message' => 'Successfully followed user',
            'following' => true,
        ]);
    }

    public function unfollow(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('unfollow', [Follow::class, $targetUser]);

        $follow = Follow::where('follower_id', $request->user()->id)
            ->where('following_id', $userId)
            ->first();

        if (! $follow) {
            return response()->json([
                'message' => 'You are not following this user',
            ], 422);
        }

        $follow->delete();

        return response()->json([
            'message' => 'Successfully unfollowed user',
            'following' => false,
        ]);
    }

    public function followers(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('viewRelations', [Follow::class, $targetUser]);

        $followers = User::whereIn('id',
            Follow::where('following_id', $userId)->pluck('follower_id')
        )->paginate(min($request->integer('per_page', 15), 100));

        return UserResource::collection($followers);
    }

    public function following(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('viewRelations', [Follow::class, $targetUser]);

        $following = User::whereIn('id',
            Follow::where('follower_id', $userId)->pluck('following_id')
        )->paginate(min($request->integer('per_page', 15), 100));

        return UserResource::collection($following);
    }

    public function isFollowing(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('viewRelations', [Follow::class, $targetUser]);

        $isFollowing = Follow::where('follower_id', $request->user()->id)
            ->where('following_id', $userId)
            ->exists();

        return response()->json([
            'user_id' => $userId,
            'is_following' => $isFollowing,
        ]);
    }

    public function checkRelationship(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('viewRelations', [Follow::class, $targetUser]);

        $currentUser = $request->user();

        $iFollowThem = Follow::where('follower_id', $currentUser->id)
            ->where('following_id', $userId)
            ->exists();

        $theyFollowMe = Follow::where('follower_id', $userId)
            ->where('following_id', $currentUser->id)
            ->exists();

        return response()->json([
            'user_id' => $userId,
            'i_follow_them' => $iFollowThem,
            'they_follow_me' => $theyFollowMe,
            'is_mutual' => $iFollowThem && $theyFollowMe,
        ]);
    }

    public function followerCount($userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('viewRelations', [Follow::class, $targetUser]);

        return response()->json([
            'user_id' => $userId,
            'followers_count' => Follow::where('following_id', $userId)->count(),
        ]);
    }

    public function followingCount($userId)
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('viewRelations', [Follow::class, $targetUser]);

        return response()->json([
            'user_id' => $userId,
            'following_count' => Follow::where('follower_id', $userId)->count(),
        ]);
    }
}
