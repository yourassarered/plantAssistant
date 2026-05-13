<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LikeResource;
use App\Models\Like;
use App\Models\Plant;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('toggle', [Like::class, $plant]);

        $like = Like::where('user_id', $request->user()->id)
            ->where('plant_id', $plantId)
            ->first();

        if ($like) {
            $like->delete();

            return response()->json([
                'message' => 'Like removed',
                'liked' => false,
            ]);
        }

        Like::create([
            'user_id' => $request->user()->id,
            'plant_id' => $plantId,
        ]);

        return response()->json([
            'message' => 'Like added',
            'liked' => true,
        ]);
    }

    public function index(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('viewPlantLikes', [Like::class, $plant]);

        $likes = Like::where('plant_id', $plantId)
            ->with('user')
            ->paginate(min($request->integer('per_page', 15), 100));

        return LikeResource::collection($likes);
    }

    public function myLikes(Request $request)
    {
        $this->authorize('viewMyLikes', Like::class);

        $likes = Like::where('user_id', $request->user()->id)
            ->with('plant', 'plant.user')
            ->orderBy('created_at', 'desc')
            ->paginate(min($request->integer('per_page', 15), 100));

        return LikeResource::collection($likes);
    }

    public function isLiked(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('viewLikeState', [Like::class, $plant]);

        $liked = Like::where('user_id', $request->user()->id)
            ->where('plant_id', $plantId)
            ->exists();

        return response()->json([
            'plant_id' => (int) $plantId,
            'liked' => $liked,
        ]);
    }

    public function count(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $user = $request->user();
        $canSeeCount = $plant->is_public || ($user && ($plant->user_id === $user->id || $user->isAdmin()));

        return response()->json([
            'plant_id' => (int) $plantId,
            'likes_count' => $canSeeCount ? Like::where('plant_id', $plantId)->count() : 0,
        ]);
    }
}
