<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModeratorAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminMetricsController extends Controller
{
    public function traffic(Request $request)
    {
        $this->authorize('manage', User::class);

        $minutes = min(max($request->integer('minutes', 60), 5), 720);
        $series = [];
        $statusBuckets = [
            '2xx' => 0,
            '4xx' => 0,
            '5xx' => 0,
        ];

        for ($i = $minutes - 1; $i >= 0; $i--) {
            $slot = now()->subMinutes($i);
            $minuteKey = $slot->format('YmdHi');
            $total = (int) Cache::get("metrics:traffic:total:{$minuteKey}", 0);

            $series[] = [
                'timestamp' => $slot->toISOString(),
                'requests_per_minute' => $total,
                'requests_per_second' => round($total / 60, 2),
            ];

            $status2xx = 0;
            $status4xx = 0;
            $status5xx = 0;
            foreach ([200, 201, 204, 400, 401, 403, 404, 405, 422, 429, 500, 502, 503] as $status) {
                $count = (int) Cache::get("metrics:traffic:status:{$minuteKey}:{$status}", 0);
                if ($status >= 200 && $status < 300) {
                    $status2xx += $count;
                } elseif ($status >= 400 && $status < 500) {
                    $status4xx += $count;
                } elseif ($status >= 500) {
                    $status5xx += $count;
                }
            }

            $statusBuckets['2xx'] += $status2xx;
            $statusBuckets['4xx'] += $status4xx;
            $statusBuckets['5xx'] += $status5xx;
        }

        $recentModeratorActions = ModeratorAuditLog::with('actor')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'actor_id' => $row->actor_id,
                'actor_name' => $row->actor?->name,
                'action' => $row->action,
                'target_type' => $row->target_type,
                'target_id' => $row->target_id,
                'created_at' => $row->created_at?->toISOString(),
            ]);

        return response()->json([
            'window_minutes' => $minutes,
            'traffic_series' => $series,
            'status_totals' => $statusBuckets,
            'recent_moderator_actions' => $recentModeratorActions,
        ]);
    }
}
