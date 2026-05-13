<?php

namespace App\Services;

use App\DTO\QueryFiltersData;
use App\Models\CareLog;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Plant;
use App\Models\Room;
use App\Models\Tip;
use App\Models\User;
use Carbon\Carbon;

class DashboardQueryService
{
    public function overview(User $user): array
    {
        $userId = $user->id;

        $plantIds = Plant::where('user_id', $userId)->pluck('id');
        $plantsCount = $plantIds->count();
        $publicPlantsCount = Plant::where('user_id', $userId)->where('is_public', true)->count();

        $topPlants = Plant::where('user_id', $userId)
            ->where('is_public', true)
            ->withCount('likes')
            ->orderByDesc('likes_count')
            ->limit(5)
            ->get(['id', 'name']);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rank' => $user->rank,
            ],
            'plants' => [
                'total' => $plantsCount,
                'public' => $publicPlantsCount,
                'private' => $plantsCount - $publicPlantsCount,
            ],
            'rooms' => Room::where('user_id', $userId)->count(),
            'social' => [
                'followers' => Follow::where('following_id', $userId)->count(),
                'following' => Follow::where('follower_id', $userId)->count(),
                'likes_received' => Like::whereIn('plant_id', $plantIds)->count(),
            ],
            'achievements' => [
                'accepted_tips' => Tip::whereIn('plant_id', $plantIds)->where('status', 'accepted')->count(),
                'rank' => $user->rank,
            ],
            'activity' => [
                'care_actions_month' => CareLog::whereIn('plant_id', $plantIds)
                    ->where('performed_at', '>=', now()->subMonth())
                    ->count(),
            ],
            'top_plants' => $topPlants->map(fn ($plant) => [
                'id' => $plant->id,
                'name' => $plant->name,
                'likes_count' => $plant->likes_count,
            ]),
        ];
    }

    public function activityStats(int $userId, QueryFiltersData $filters): array
    {
        $startDate = now()->subDays($filters->days)->startOfDay();
        $endDate = now()->endOfDay();

        $careLogs = CareLog::whereIn('plant_id', Plant::where('user_id', $userId)->pluck('id'))
            ->whereBetween('performed_at', [$startDate, $endDate])
            ->get();

        $activityByDay = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $activityByDay[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }

        foreach ($careLogs as $log) {
            $date = $log->performed_at->toDateString();
            if (isset($activityByDay[$date])) {
                $activityByDay[$date]++;
            }
        }

        return [
            'period_days' => $filters->days,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'total_actions' => $careLogs->count(),
            'activity_by_day' => $activityByDay,
            'care_by_type' => [
                'watering' => $careLogs->where('type', 'watering')->count(),
                'fertilizing' => $careLogs->where('type', 'fertilizing')->count(),
                'pruning' => $careLogs->where('type', 'pruning')->count(),
                'rotation' => $careLogs->where('type', 'rotation')->count(),
            ],
            'average_actions_per_day' => round($careLogs->count() / $filters->days, 2),
        ];
    }

    public function plantHealthStats(int $userId): array
    {
        $today = now()->toDateString();
        $plants = Plant::where('user_id', $userId)->with('careSettings')->get();

        $wellCaredFor = 0;
        $needsAttention = 0;
        $needsUrgentAttention = 0;

        foreach ($plants as $plant) {
            $hasOverdueTasks = false;
            $maxOverdueDays = 0;

            foreach ($plant->careSettings as $setting) {
                if (! $setting->is_enabled) {
                    continue;
                }

                $baseDate = $setting->last_done_at ?? $plant->planted_at;
                $nextDueDate = $baseDate->copy()->addDays($setting->interval_days);
                $overdueDays = $nextDueDate->diffInDays(Carbon::parse($today), false);

                if ($overdueDays > 0) {
                    $hasOverdueTasks = true;
                    $maxOverdueDays = max($maxOverdueDays, $overdueDays);
                }
            }

            if (! $hasOverdueTasks) {
                $wellCaredFor++;
            } elseif ($maxOverdueDays > 7) {
                $needsUrgentAttention++;
            } else {
                $needsAttention++;
            }
        }

        return [
            'total_plants' => $plants->count(),
            'well_cared_for' => $wellCaredFor,
            'needs_attention' => $needsAttention,
            'needs_urgent_attention' => $needsUrgentAttention,
            'health_percentage' => $plants->count() > 0
                ? round(($wellCaredFor / $plants->count()) * 100, 2)
                : 100,
        ];
    }
}
