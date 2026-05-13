<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlantResource;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Plant;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    /**
     * Общая лента публичных растений
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $plants = Plant::where('is_public', true)
            ->with(['user.role', 'room', 'latestImage', 'likes'])
            ->withCount('likes')
            ->orderBy('created_at', 'desc');

        // Фильтр по названию
        if ($request->has('search')) {
            $search = $request->search;
            $plants->where('name', 'ilike', "%{$search}%");
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'created_at');
        if ($sortBy === 'likes') {
            $plants->orderBy('likes_count', 'desc');
        } else {
            $plants->orderBy('created_at', 'desc');
        }

        $plantsCollection = $plants->paginate(min($request->integer('per_page', 15), 100));

        // Добавляем информацию о том, лайкнул ли текущий пользователь
        $likedPlantIds = Like::where('user_id', $userId)
            ->pluck('plant_id')
            ->toArray();

        return response()->json([
            'data' => PlantResource::collection($plantsCollection),
            'liked_plants' => $likedPlantIds,
            'pagination' => [
                'current_page' => $plantsCollection->currentPage(),
                'per_page' => $plantsCollection->perPage(),
                'total' => $plantsCollection->total(),
                'last_page' => $plantsCollection->lastPage(),
            ],
        ]);
    }

    /**
     * Персонализированная лента (растения от подписок)
     */
    public function personal(Request $request)
    {
        $userId = $request->user()->id;

        // Получаем ID пользователей, на которых подписан текущий пользователь
        $followingIds = Follow::where('follower_id', $userId)
            ->pluck('following_id')
            ->toArray();

        // Добавляем самого себя
        $followingIds[] = $userId;

        $plants = Plant::where('is_public', true)
            ->whereIn('user_id', $followingIds)
            ->with(['user.role', 'room', 'latestImage', 'likes'])
            ->withCount('likes')
            ->orderBy('created_at', 'desc');

        // Фильтр по названию
        if ($request->has('search')) {
            $search = $request->search;
            $plants->where('name', 'ilike', "%{$search}%");
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'created_at');
        if ($sortBy === 'likes') {
            $plants->orderBy('likes_count', 'desc');
        } else {
            $plants->orderBy('created_at', 'desc');
        }

        $plantsCollection = $plants->paginate(min($request->integer('per_page', 15), 100));

        // Добавляем информацию о том, лайкнул ли текущий пользователь
        $likedPlantIds = Like::where('user_id', $userId)
            ->pluck('plant_id')
            ->toArray();

        return response()->json([
            'data' => PlantResource::collection($plantsCollection),
            'liked_plants' => $likedPlantIds,
            'pagination' => [
                'current_page' => $plantsCollection->currentPage(),
                'per_page' => $plantsCollection->perPage(),
                'total' => $plantsCollection->total(),
                'last_page' => $plantsCollection->lastPage(),
            ],
        ]);
    }

    /**
     * Популярные растения (по количеству лайков)
     */
    public function trending(Request $request)
    {
        $userId = $request->user()->id;

        $plants = Plant::where('is_public', true)
            ->with(['user.role', 'room', 'latestImage', 'likes'])
            ->withCount('likes')
            ->orderBy('likes_count', 'desc');

        // Фильтр по названию
        if ($request->has('search')) {
            $search = $request->search;
            $plants->where('name', 'ilike', "%{$search}%");
        }

        // Период для трендов (по умолчанию за последнюю неделю)
        $days = min(max($request->integer('days', 7), 1), 90);
        $startDate = now()->subDays($days)->startOfDay();

        $plants->where('created_at', '>=', $startDate);

        $plantsCollection = $plants->paginate(min($request->integer('per_page', 15), 100));

        // Добавляем информацию о том, лайкнул ли текущий пользователь
        $likedPlantIds = Like::where('user_id', $userId)
            ->pluck('plant_id')
            ->toArray();

        return response()->json([
            'period_days' => $days,
            'data' => PlantResource::collection($plantsCollection),
            'liked_plants' => $likedPlantIds,
            'pagination' => [
                'current_page' => $plantsCollection->currentPage(),
                'per_page' => $plantsCollection->perPage(),
                'total' => $plantsCollection->total(),
                'last_page' => $plantsCollection->lastPage(),
            ],
        ]);
    }

    /**
     * Растения конкретного пользователя (публичные)
     */
    public function userPlants(Request $request, $userId)
    {
        $currentUserId = $request->user()->id;

        $plants = Plant::where('user_id', $userId)
            ->where('is_public', true)
            ->with(['user.role', 'room', 'latestImage', 'likes'])
            ->withCount('likes')
            ->orderBy('created_at', 'desc');

        // Фильтр по названию
        if ($request->has('search')) {
            $search = $request->search;
            $plants->where('name', 'ilike', "%{$search}%");
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'created_at');
        if ($sortBy === 'likes') {
            $plants->orderBy('likes_count', 'desc');
        } else {
            $plants->orderBy('created_at', 'desc');
        }

        $plantsCollection = $plants->paginate(min($request->integer('per_page', 15), 100));

        // Добавляем информацию о том, лайкнул ли текущий пользователь
        $likedPlantIds = Like::where('user_id', $currentUserId)
            ->pluck('plant_id')
            ->toArray();

        return response()->json([
            'user_id' => $userId,
            'data' => PlantResource::collection($plantsCollection),
            'liked_plants' => $likedPlantIds,
            'pagination' => [
                'current_page' => $plantsCollection->currentPage(),
                'per_page' => $plantsCollection->perPage(),
                'total' => $plantsCollection->total(),
                'last_page' => $plantsCollection->lastPage(),
            ],
        ]);
    }

    /**
     * Растения с советами (растения, для которых есть советы)
     */
    public function withTips(Request $request)
    {
        $userId = $request->user()->id;

        $plants = Plant::where('is_public', true)
            ->whereHas('tips')
            ->with(['user.role', 'room', 'latestImage', 'likes', 'tips' => function ($query) {
                $query->where('status', 'accepted');
            }])
            ->withCount('likes')
            ->orderBy('created_at', 'desc');

        $plantsCollection = $plants->paginate(min($request->integer('per_page', 15), 100));

        // Добавляем информацию о том, лайкнул ли текущий пользователь
        $likedPlantIds = Like::where('user_id', $userId)
            ->pluck('plant_id')
            ->toArray();

        return response()->json([
            'data' => PlantResource::collection($plantsCollection),
            'liked_plants' => $likedPlantIds,
            'pagination' => [
                'current_page' => $plantsCollection->currentPage(),
                'per_page' => $plantsCollection->perPage(),
                'total' => $plantsCollection->total(),
                'last_page' => $plantsCollection->lastPage(),
            ],
        ]);
    }

    /**
     * Рекомендации для пользователя (на основе подписок и лайков)
     */
    public function recommendations(Request $request)
    {
        $userId = $request->user()->id;

        // Получаем ID пользователей, на которых подписан текущий пользователь
        $followingIds = Follow::where('follower_id', $userId)
            ->pluck('following_id')
            ->toArray();

        // Получаем растения, которые лайкнули люди, на которых мы подписаны
        // и которые мы сами еще не лайкнули
        $likedByFollowing = Like::whereIn('user_id', $followingIds)
            ->pluck('plant_id')
            ->toArray();

        $myLikes = Like::where('user_id', $userId)
            ->pluck('plant_id')
            ->toArray();

        $recommendedPlantIds = array_diff($likedByFollowing, $myLikes);

        $plants = Plant::where('is_public', true)
            ->whereIn('id', $recommendedPlantIds)
            ->with(['user.role', 'room', 'latestImage', 'likes'])
            ->withCount('likes')
            ->orderBy('likes_count', 'desc');

        $plantsCollection = $plants->paginate(min($request->integer('per_page', 15), 100));

        return response()->json([
            'data' => PlantResource::collection($plantsCollection),
            'liked_plants' => $myLikes,
            'pagination' => [
                'current_page' => $plantsCollection->currentPage(),
                'per_page' => $plantsCollection->perPage(),
                'total' => $plantsCollection->total(),
                'last_page' => $plantsCollection->lastPage(),
            ],
        ]);
    }

    /**
     * Лайкнутые растения текущего пользователя
     */
    public function likedPlants(Request $request)
    {
        $userId = $request->user()->id;

        $plants = Plant::where('is_public', true)
            ->whereIn('id',
                Like::where('user_id', $userId)->pluck('plant_id')
            )
            ->with(['user.role', 'room', 'latestImage', 'likes'])
            ->withCount('likes')
            ->orderBy('created_at', 'desc');

        // Фильтр по названию
        if ($request->has('search')) {
            $search = $request->search;
            $plants->where('name', 'ilike', "%{$search}%");
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'created_at');
        if ($sortBy === 'likes') {
            $plants->orderBy('likes_count', 'desc');
        } else {
            $plants->orderBy('created_at', 'desc');
        }

        $plantsCollection = $plants->paginate(min($request->integer('per_page', 15), 100));

        // Все растения в этом списке лайкнуты текущим пользователем
        $likedPlantIds = $plantsCollection->pluck('id')->toArray();

        return response()->json([
            'data' => PlantResource::collection($plantsCollection),
            'liked_plants' => $likedPlantIds,
            'pagination' => [
                'current_page' => $plantsCollection->currentPage(),
                'per_page' => $plantsCollection->perPage(),
                'total' => $plantsCollection->total(),
                'last_page' => $plantsCollection->lastPage(),
            ],
        ]);
    }
}
