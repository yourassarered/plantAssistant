<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LikeResource;
use App\Models\Like;
use App\Models\Plant;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    /**
     * Поставить/убрать лайк на растение
     */
    public function toggle(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);

        // Можно лайкнуть только публичные растения
        if (!$plant->is_public) {
            return response()->json([
                'message' => 'Can only like public plants',
            ], 403);
        }

        // Нельзя лайкнуть свои растения
        if ($plant->user_id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot like your own plants',
            ], 422);
        }

        $like = Like::where('user_id', $request->user()->id)
            ->where('plant_id', $plantId)
            ->first();

        if ($like) {
            // Лайк уже есть, удаляем его
            $like->delete();

            return response()->json([
                'message' => 'Like removed',
                'liked' => false,
            ]);
        } else {
            // Создаём лайк
            Like::create([
                'user_id' => $request->user()->id,
                'plant_id' => $plantId,
            ]);

            return response()->json([
                'message' => 'Like added',
                'liked' => true,
            ]);
        }
    }

    /**
     * Список пользователей, лайкнувших растение
     */
    public function index(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);

        // Может просмотреть лайки только для своих растений или публичных
        if ($plant->user_id !== $request->user()->id && !$plant->is_public) {
            abort(403, 'Unauthorized');
        }

        $likes = Like::where('plant_id', $plantId)
            ->with('user')
            ->paginate($request->get('per_page', 15));

        return LikeResource::collection($likes);
    }

    /**
     * Растения, лайкнутые текущим пользователем
     */
    public function myLikes(Request $request)
    {
        $likes = Like::where('user_id', $request->user()->id)
            ->with('plant', 'plant.user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return LikeResource::collection($likes);
    }

    /**
     * Проверка, лайкнул ли пользователь это растение
     */
    public function isLiked(Request $request, $plantId)
    {
        $liked = Like::where('user_id', $request->user()->id)
            ->where('plant_id', $plantId)
            ->exists();

        return response()->json([
            'plant_id' => $plantId,
            'liked' => $liked,
        ]);
    }

    /**
     * Количество лайков для растения
     */
    public function count($plantId)
    {
        $plant = Plant::findOrFail($plantId);

        // Может просмотреть количество лайков только для публичных растений или своих
        if (!$plant->is_public) {
            abort(403, 'Unauthorized');
        }

        $count = Like::where('plant_id', $plantId)->count();

        return response()->json([
            'plant_id' => $plantId,
            'likes_count' => $count,
        ]);
    }
}