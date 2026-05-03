<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    /**
     * Подписаться на пользователя
     */
    public function follow(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $currentUser = $request->user();

        // Нельзя подписаться на себя
        if ($targetUser->id === $currentUser->id) {
            return response()->json([
                'message' => 'You cannot follow yourself',
            ], 422);
        }

        // Проверяем, не подписаны ли уже
        $follow = Follow::where('follower_id', $currentUser->id)
            ->where('following_id', $userId)
            ->first();

        if ($follow) {
            return response()->json([
                'message' => 'You are already following this user',
            ], 422);
        }

        Follow::create([
            'follower_id' => $currentUser->id,
            'following_id' => $userId,
        ]);

        return response()->json([
            'message' => 'Successfully followed user',
            'following' => true,
        ]);
    }

    /**
     * Отписаться от пользователя
     */
    public function unfollow(Request $request, $userId)
    {
        $targetUser = User::findOrFail($userId);
        $currentUser = $request->user();

        $follow = Follow::where('follower_id', $currentUser->id)
            ->where('following_id', $userId)
            ->first();

        if (!$follow) {
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

    /**
     * Список подписчиков пользователя
     */
    public function followers(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $followers = User::whereIn('id',
            Follow::where('following_id', $userId)->pluck('follower_id')
        )
            ->paginate($request->get('per_page', 15));

        return UserResource::collection($followers);
    }

    /**
     * Список пользователей, на которых подписан пользователь
     */
    public function following(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $following = User::whereIn('id',
            Follow::where('follower_id', $userId)->pluck('following_id')
        )
            ->paginate($request->get('per_page', 15));

        return UserResource::collection($following);
    }

    /**
     * Проверка подписки (подписан ли текущий пользователь на целевого)
     */
    public function isFollowing(Request $request, $userId)
    {
        $isFollowing = Follow::where('follower_id', $request->user()->id)
            ->where('following_id', $userId)
            ->exists();

        return response()->json([
            'user_id' => $userId,
            'is_following' => $isFollowing,
        ]);
    }

    /**
     * Проверить отношение между пользователями
     */
    public function checkRelationship(Request $request, $userId)
    {
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

    /**
     * Количество подписчиков пользователя
     */
    public function followerCount($userId)
    {
        $count = Follow::where('following_id', $userId)->count();

        return response()->json([
            'user_id' => $userId,
            'followers_count' => $count,
        ]);
    }

    /**
     * Количество подписок пользователя
     */
    public function followingCount($userId)
    {
        $count = Follow::where('follower_id', $userId)->count();

        return response()->json([
            'user_id' => $userId,
            'following_count' => $count,
        ]);
    }
}