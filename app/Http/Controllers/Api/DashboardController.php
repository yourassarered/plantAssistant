<?php

namespace App\Http\Controllers\Api;

use App\DTO\QueryFiltersData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DashboardQueryRequest;
use App\Services\DashboardQueryService;
use App\Services\TaggedCacheService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardQueryService $service,
        private readonly TaggedCacheService $cache
    ) {}

    public function overview(Request $request)
    {
        $user = $request->user();
        $this->authorize('view', $user);

        return response()->json(
            $this->cache->remember(
                ['dashboard'],
                "dashboard:overview:{$user->id}",
                120,
                fn () => $this->service->overview($user)
            )
        );
    }

    public function activityStats(DashboardQueryRequest $request)
    {
        $this->authorize('view', $request->user());

        $userId = $request->user()->id;
        $filters = QueryFiltersData::fromDashboardRequest($request);

        return response()->json(
            $this->cache->remember(
                ['dashboard'],
                "dashboard:activity:{$userId}:{$filters->days}",
                120,
                fn () => $this->service->activityStats($userId, $filters)
            )
        );
    }

    public function plantHealthStats(Request $request)
    {
        $this->authorize('view', $request->user());

        $userId = $request->user()->id;

        return response()->json(
            $this->cache->remember(
                ['dashboard'],
                "dashboard:health:{$userId}",
                120,
                fn () => $this->service->plantHealthStats($userId)
            )
        );
    }
}
