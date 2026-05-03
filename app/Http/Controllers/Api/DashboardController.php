<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\Room;
use App\Models\CareLog;
use App\Models\Like;
use App\Models\Tip;
use App\Models\Follow;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Общая статистика пользователя
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        $plantsCount = Plant::where('user_id', $userId)->count();
        $roomsCount = Room::where('user_id', $userId)->count();
        $publicPlantsCount = Plant::where('user_id', $userId)->where('is_public', true)->count();

        $followersCount = Follow::where('following_id', $userId)->count();
        $followingCount = Follow::where('follower_id', $userId)->count();

        // Количество полученных лайков
        $likesCount = Like::whereIn('plant_id', 
            Plant::where('user_id', $userId)->pluck('id')
        )->count();

        // Количество принятых советов
        $acceptedTipsCount = Tip::whereIn('plant_id',
            Plant::where('user_id', $userId)->pluck('id')
        )->where('status', 'accepted')->count();

        // Последние действия (логи ухода) за последний месяц
        $logsCount = CareLog::whereIn('plant_id',
            Plant::where('user_id', $userId)->pluck('id')
        )->where('performed_at', '>=', now()->subMonth())->count();

        // Распределение лайков по растениям
        $topPlants = Plant::where('user_id', $userId)
            ->where('is_public', true)
            ->withCount('likes')
            ->orderBy('likes_count', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'likes_count']);

        return response()->json([
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
            'rooms' => $roomsCount,
            'social' => [
                'followers' => $followersCount,
                'following' => $followingCount,
                'likes_received' => $likesCount,
            ],
            'achievements' => [
                'accepted_tips' => $acceptedTipsCount,
                'rank' => $user->rank,
            ],
            'activity' => [
                'care_actions_month' => $logsCount,
            ],
            'top_plants' => $topPlants->map(fn($plant) => [
                'id' => $plant->id,
                'name' => $plant->name,
                'likes_count' => $plant->likes_count,
            ]),
        ]);
    }

    /**
     * Статистика активности пользователя
     */
    public function activityStats(Request $request)
    {
        $userId = $request->user()->id;
        $days = $request->get('days', 30);

        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Логи ухода по дням
        $careLogs = CareLog::whereIn('plant_id',
            Plant::where('user_id', $userId)->pluck('id')
        )
            ->where('performed_at', '>=', $startDate)
            ->where('performed_at', '<=', $endDate)
            ->get();

        // Группируем по дням
        $activityByDay = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $activityByDay[$dateStr] = 0;
            $currentDate->addDay();
        }

        foreach ($careLogs as $log) {
            $dateStr = $log->performed_at->toDateString();
            if (isset($activityByDay[$dateStr])) {
                $activityByDay[$dateStr]++;
            }
        }

        // Распределение по типам ухода
        $careByType = [
            'watering' => $careLogs->where('type', 'watering')->count(),
            'fertilizing' => $careLogs->where('type', 'fertilizing')->count(),
            'pruning' => $careLogs->where('type', 'pruning')->count(),
            'rotation' => $careLogs->where('type', 'rotation')->count(),
        ];

        return response()->json([
            'period_days' => $days,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'total_actions' => $careLogs->count(),
            'activity_by_day' => $activityByDay,
            'care_by_type' => $careByType,
            'average_actions_per_day' => round($careLogs->count() / $days, 2),
        ]);
    }

    /**
     * Статистика здоровья растений (количество растений по активности ухода)
     */
    public function plantHealthStats(Request $request)
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();

        $plants = Plant::where('user_id', $userId)
            ->with('careSettings')
            ->get();

        $wellCaredFor = 0;      // Все ухода в порядке
        $needsAttention = 0;    // Есть задачи на сегодня или просрочено до 7 дней
        $needsUrgentAttention = 0; // Просрочено более 7 дней

        foreach ($plants as $plant) {
            $hasOverdueTasks = false;
            $maxOverdueDays = 0;

            foreach ($plant->careSettings as $setting) {
                if (!$setting->is_enabled) {
                    continue;
                }

                $baseDate = $setting->last_done_at ?? $plant->planted_at;
                $nextDueDate = $baseDate->copy()->addDays($setting->interval_days);

                $overdueDays = Carbon::parse($today)->diffInDays($nextDueDate, false);

                if ($overdueDays > 0) {
                    $hasOverdueTasks = true;
                    $maxOverdueDays = max($maxOverdueDays, $overdueDays);
                }
            }

            if (!$hasOverdueTasks) {
                $wellCaredFor++;
            } elseif ($maxOverdueDays > 7) {
                $needsUrgentAttention++;
            } else {
                $needsAttention++;
            }
        }

        return response()->json([
            'total_plants' => $plants->count(),
            'well_cared_for' => $wellCaredFor,
            'needs_attention' => $needsAttention,
            'needs_urgent_attention' => $needsUrgentAttention,
            'health_percentage' => $plants->count() > 0 
                ? round(($wellCaredFor / $plants->count()) * 100, 2)
                : 100,
        ]);
    }
}