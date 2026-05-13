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
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:index:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->service->publicFeed($userId, $filters)
        );

        return $this->responseFromPayload($payload);
    }

    public function personal(FeedQueryRequest $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:personal:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->service->personalFeed($userId, $filters)
        );

        return $this->responseFromPayload($payload);
    }

    public function trending(FeedQueryRequest $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:trending:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->service->trendingFeed($userId, $filters)
        );

        return $this->responseFromPayload($payload, [
            'period_days' => $payload['period_days'] ?? $filters->days,
        ]);
    }

    public function userPlants(FeedQueryRequest $request, $userId)
    {
        $this->authorize('viewAny', Plant::class);

        $currentUserId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:user:{$currentUserId}:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->service->userPlantsFeed($currentUserId, (int) $userId, $filters)
        );

        return $this->responseFromPayload($payload, ['user_id' => (int) $userId]);
    }

    public function withTips(FeedQueryRequest $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:with_tips:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->service->withTipsFeed($userId, $filters)
        );

        return $this->responseFromPayload($payload);
    }

    public function recommendations(FeedQueryRequest $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:recommendations:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->service->recommendationsFeed($userId, $filters)
        );

        return $this->responseFromPayload($payload);
    }

    public function likedPlants(FeedQueryRequest $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromFeedRequest($request);

        $payload = $this->cache->remember(
            ['feed'],
            "feed:liked:{$userId}:".md5(json_encode($request->query())),
            120,
            fn () => $this->service->likedPlantsFeed($userId, $filters)
        );

        return $this->responseFromPayload($payload);
    }

    private function responseFromPayload(array $payload, array $extra = [])
    {
        $paginator = $payload['paginator'];

        return response()->json(array_merge($extra, [
            'data' => PlantResource::collection($paginator),
            'liked_plants' => $payload['liked_plant_ids'] ?? [],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]));
    }
}
