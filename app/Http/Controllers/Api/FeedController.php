<?php

namespace App\Http\Controllers\Api;

use App\DTO\QueryFiltersData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FeedQueryRequest;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use App\Services\FeedQueryService;
use App\Services\TaggedCacheService;

class FeedController extends Controller
{
    public function __construct(
        private readonly FeedQueryService $service,
        private readonly TaggedCacheService $cache
    ) {}

    public function index(FeedQueryRequest $request)
    {
        $userId = auth('sanctum')->user()?->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            'feed:v7:index:'.($userId ?? 'guest').':'.md5(json_encode($request->query())),
            120,
            fn () => $this->packPayload($this->service->publicFeed($userId, $filters))
        );

        return response()->json($payload);
    }

    public function personal(FeedQueryRequest $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:v7:personal:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->packPayload($this->service->personalFeed($userId, $filters))
        );

        return response()->json($payload);
    }

    public function trending(FeedQueryRequest $request)
    {
        $userId = auth('sanctum')->user()?->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            'feed:v7:trending:'.($userId ?? 'guest').':'.md5(json_encode($request->query())),
            120,
            fn () => $this->packPayload($this->service->trendingFeed($userId, $filters))
        );

        return response()->json($payload);
    }

    public function userPlants(FeedQueryRequest $request, $userId)
    {
        $currentUserId = auth('sanctum')->user()?->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            'feed:v7:user:'.($currentUserId ?? 'guest').":{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->packPayload($this->service->userPlantsFeed($currentUserId, (int) $userId, $filters))
        );

        return response()->json(array_merge($payload, ['user_id' => (int) $userId]));
    }

    public function withTips(FeedQueryRequest $request)
    {
        $userId = auth('sanctum')->user()?->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            'feed:v7:with_tips:'.($userId ?? 'guest').':'.md5(json_encode($request->query())),
            120,
            fn () => $this->packPayload($this->service->withTipsFeed($userId, $filters))
        );

        return response()->json($payload);
    }

    public function recommendations(FeedQueryRequest $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:v7:recommendations:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->packPayload($this->service->recommendationsFeed($userId, $filters))
        );

        return response()->json($payload);
    }

    public function likedPlants(FeedQueryRequest $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:v7:liked:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->packPayload($this->service->likedPlantsFeed($userId, $filters))
        );

        return response()->json($payload);
    }

    private function packPayload(array $payload): array
    {
        $paginator = $payload['paginator'];
        $likedPlantIds = collect($payload['liked_plant_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->flip();

        // В кэш кладем только plain array, иначе nested JsonResource ломается после восстановления из cache.
        $resolved = PlantResource::collection($paginator->getCollection())
            ->response()
            ->getData(true);
        $data = is_array($resolved) && array_key_exists('data', $resolved) ? $resolved['data'] : $resolved;
        $data = collect($data)->map(function (array $plant) use ($likedPlantIds) {
            $plant['user_liked'] = $likedPlantIds->has((int) $plant['id']);

            return $plant;
        })->all();

        $response = [
            'data' => $data,
            'liked_plants' => $payload['liked_plant_ids'] ?? [],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];

        if (array_key_exists('period_days', $payload)) {
            $response['period_days'] = $payload['period_days'];
        }

        return $response;
    }
}
