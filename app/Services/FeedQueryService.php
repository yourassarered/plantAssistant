<?php

namespace App\Services;

use App\DTO\QueryFiltersData;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Plant;

class FeedQueryService
{
    public function publicFeed(?int $userId, QueryFiltersData $filters): array
    {
        $query = Plant::where('is_public', true)
            ->whereHas('user', fn ($builder) => $builder->whereNull('blocked_at'))
            ->with(['user.role', 'room', 'latestImage', 'careSettings', 'likes'])
            ->withCount($this->reportSummaryCounts())
            ->withCount('likes');

        $this->applyCommonFilters($query, $filters);
        $this->applySort($query, $filters);

        return [
            'paginator' => $query->paginate($filters->perPage),
            'liked_plant_ids' => $this->likedIds($userId),
        ];
    }

    public function personalFeed(int $userId, QueryFiltersData $filters): array
    {
        $followingIds = Follow::where('follower_id', $userId)->pluck('following_id')->toArray();
        $followingIds[] = $userId;

        $query = Plant::where('is_public', true)
            ->whereIn('user_id', $followingIds)
            ->with(['user.role', 'room', 'latestImage', 'careSettings', 'likes'])
            ->withCount($this->reportSummaryCounts())
            ->withCount('likes');

        $this->applyCommonFilters($query, $filters);
        $this->applySort($query, $filters);

        return [
            'paginator' => $query->paginate($filters->perPage),
            'liked_plant_ids' => $this->likedIds($userId),
        ];
    }

    public function trendingFeed(?int $userId, QueryFiltersData $filters): array
    {
        $query = Plant::where('is_public', true)
            ->where('created_at', '>=', now()->subDays($filters->days)->startOfDay())
            ->with(['user.role', 'room', 'latestImage', 'careSettings', 'likes'])
            ->withCount($this->reportSummaryCounts())
            ->withCount('likes')
            ->orderByDesc('likes_count');

        $this->applyCommonFilters($query, $filters);

        return [
            'paginator' => $query->paginate($filters->perPage),
            'liked_plant_ids' => $this->likedIds($userId),
            'period_days' => $filters->days,
        ];
    }

    public function userPlantsFeed(?int $currentUserId, int $targetUserId, QueryFiltersData $filters): array
    {
        $query = Plant::where('user_id', $targetUserId)
            ->where('is_public', true)
            ->with(['user.role', 'room', 'latestImage', 'careSettings', 'likes'])
            ->withCount($this->reportSummaryCounts())
            ->withCount('likes');

        $this->applyCommonFilters($query, $filters);
        $this->applySort($query, $filters);

        return [
            'paginator' => $query->paginate($filters->perPage),
            'liked_plant_ids' => $this->likedIds($currentUserId),
        ];
    }

    public function withTipsFeed(?int $userId, QueryFiltersData $filters): array
    {
        $query = Plant::where('is_public', true)
            ->whereHas('tips')
            ->with(['user.role', 'room', 'latestImage', 'careSettings', 'likes', 'tips' => function ($q) {
                $q->where('status', 'accepted');
            }])
            ->withCount($this->reportSummaryCounts())
            ->withCount('likes')
            ->orderByDesc('created_at');

        $this->applyCommonFilters($query, $filters);

        return [
            'paginator' => $query->paginate($filters->perPage),
            'liked_plant_ids' => $this->likedIds($userId),
        ];
    }

    public function recommendationsFeed(int $userId, QueryFiltersData $filters): array
    {
        $followingIds = Follow::where('follower_id', $userId)->pluck('following_id')->toArray();
        $likedByFollowing = Like::whereIn('user_id', $followingIds)->pluck('plant_id')->toArray();
        $myLikes = $this->likedIds($userId);
        $recommendedPlantIds = array_values(array_diff($likedByFollowing, $myLikes));

        $query = Plant::where('is_public', true)
            ->whereIn('id', $recommendedPlantIds)
            ->with(['user.role', 'room', 'latestImage', 'careSettings', 'likes'])
            ->withCount($this->reportSummaryCounts())
            ->withCount('likes')
            ->orderByDesc('likes_count');

        $this->applyCommonFilters($query, $filters);

        return [
            'paginator' => $query->paginate($filters->perPage),
            'liked_plant_ids' => $myLikes,
        ];
    }

    public function likedPlantsFeed(int $userId, QueryFiltersData $filters): array
    {
        $query = Plant::where('is_public', true)
            ->whereIn('id', Like::where('user_id', $userId)->pluck('plant_id'))
            ->with(['user.role', 'room', 'latestImage', 'careSettings', 'likes'])
            ->withCount($this->reportSummaryCounts())
            ->withCount('likes');

        $this->applyCommonFilters($query, $filters);
        $this->applySort($query, $filters);

        $paginator = $query->paginate($filters->perPage);

        return [
            'paginator' => $paginator,
            'liked_plant_ids' => $paginator->pluck('id')->toArray(),
        ];
    }

    private function likedIds(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

        return Like::where('user_id', $userId)->pluck('plant_id')->toArray();
    }

    private function applyCommonFilters($query, QueryFiltersData $filters): void
    {
        if ($filters->search) {
            $search = $filters->search;

            $query->where(function ($query) use ($search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('name', 'ilike', "%{$search}%");
                    });
            });
        }
    }

    private function applySort($query, QueryFiltersData $filters): void
    {
        if ($filters->sortBy === 'likes') {
            $query->orderByDesc('likes_count');

            return;
        }

        if (in_array($filters->sortBy, ['created_at', 'name', 'planted_at'], true)) {
            $query->orderBy($filters->sortBy, $filters->sortOrder);

            return;
        }

        $query->orderBy('created_at', $filters->sortOrder);
    }

    private function reportSummaryCounts(): array
    {
        return [
            'reports as pending_reports_count' => fn ($query) => $query->where('status', 'pending'),
            'reports as accepted_reports_count' => fn ($query) => $query->where('status', 'accepted'),
            'reports as rejected_reports_count' => fn ($query) => $query->where('status', 'rejected'),
        ];
    }
}
