<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CareScheduleController extends Controller
{
    public function plantSchedule(Request $request, $plantId)
    {
        $plant = Plant::with('careSettings')->findOrFail($plantId);
        $this->authorize('update', $plant);

        $startDate = $request->has('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->startOfMonth();

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfMonth();

        $schedule = $this->generateSchedule($plant, $startDate, $endDate);

        return response()->json([
            'plant_id' => $plant->id,
            'plant_name' => $plant->name,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'schedule' => $schedule,
            'total_tasks' => collect($schedule)->sum(fn ($day) => count($day['tasks'])),
        ]);
    }

    public function todaysCare(Request $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $today = now()->toDateString();

        $plants = Plant::where('user_id', $userId)
            ->with(['careSettings', 'room'])
            ->get();

        $plantsNeedingCare = [];
        $totalTasks = 0;

        foreach ($plants as $plant) {
            $tasksForPlant = $this->getTasksForDate($plant, $today);

            if (! empty($tasksForPlant)) {
                $plantsNeedingCare[] = [
                    'plant' => [
                        'id' => $plant->id,
                        'name' => $plant->name,
                        'height' => $plant->height,
                        'room' => $plant->room ? [
                            'id' => $plant->room->id,
                            'name' => $plant->room->name,
                        ] : null,
                    ],
                    'tasks' => $tasksForPlant,
                ];
                $totalTasks += count($tasksForPlant);
            }
        }

        usort($plantsNeedingCare, function ($a, $b) {
            $aOverdue = collect($a['tasks'])->filter(fn ($t) => $t['is_overdue'])->count();
            $bOverdue = collect($b['tasks'])->filter(fn ($t) => $t['is_overdue'])->count();

            return $bOverdue <=> $aOverdue;
        });

        return response()->json([
            'date' => $today,
            'plants_count' => count($plantsNeedingCare),
            'total_tasks' => $totalTasks,
            'plants' => $plantsNeedingCare,
        ]);
    }

    public function monthSchedule(Request $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $month = min(max($request->integer('month', now()->month), 1), 12);
        $year = min(max($request->integer('year', now()->year), 2000), 2100);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        $plants = Plant::where('user_id', $userId)->with('careSettings')->get();

        $schedule = [];
        $statistics = [
            'total_tasks' => 0,
            'by_type' => [
                'watering' => 0,
                'fertilizing' => 0,
                'pruning' => 0,
                'rotation' => 0,
            ],
            'by_plant' => [],
        ];

        foreach ($plants as $plant) {
            $plantTasks = 0;
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dateStr = $currentDate->toDateString();
                $tasksForDate = $this->getTasksForDate($plant, $dateStr);

                if (! empty($tasksForDate)) {
                    if (! isset($schedule[$dateStr])) {
                        $schedule[$dateStr] = [];
                    }

                    foreach ($tasksForDate as $task) {
                        $schedule[$dateStr][] = [
                            'plant_id' => $plant->id,
                            'plant_name' => $plant->name,
                            'type' => $task['type'],
                            'setting_id' => $task['id'],
                        ];

                        $statistics['by_type'][$task['type']]++;
                        $plantTasks++;
                    }
                }

                $currentDate->addDay();
            }

            if ($plantTasks > 0) {
                $statistics['by_plant'][] = [
                    'plant_id' => $plant->id,
                    'plant_name' => $plant->name,
                    'tasks_count' => $plantTasks,
                ];
                $statistics['total_tasks'] += $plantTasks;
            }
        }

        $formattedSchedule = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $formattedSchedule[] = [
                'date' => $dateStr,
                'tasks' => $schedule[$dateStr] ?? [],
            ];
            $currentDate->addDay();
        }

        return response()->json([
            'month' => $month,
            'year' => $year,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'schedule' => $formattedSchedule,
            'statistics' => $statistics,
        ]);
    }

    public function upcomingCare(Request $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $days = min(max($request->integer('days', 7), 1), 90);

        $startDate = now()->startOfDay();
        $endDate = $startDate->copy()->addDays($days)->endOfDay();

        $plants = Plant::where('user_id', $userId)
            ->with(['careSettings', 'room'])
            ->get();

        $schedule = [];

        foreach ($plants as $plant) {
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dateStr = $currentDate->toDateString();
                $tasksForDate = $this->getTasksForDate($plant, $dateStr);

                if (! empty($tasksForDate)) {
                    if (! isset($schedule[$dateStr])) {
                        $schedule[$dateStr] = ['date' => $dateStr, 'tasks' => []];
                    }

                    foreach ($tasksForDate as $task) {
                        $schedule[$dateStr]['tasks'][] = [
                            'plant_id' => $plant->id,
                            'plant_name' => $plant->name,
                            'room_name' => $plant->room?->name,
                            'type' => $task['type'],
                            'setting_id' => $task['id'],
                        ];
                    }
                }

                $currentDate->addDay();
            }
        }

        ksort($schedule);

        return response()->json([
            'period_days' => $days,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'tasks' => array_values($schedule),
            'total_tasks' => collect($schedule)->sum(fn ($day) => count($day['tasks'])),
        ]);
    }

    public function overdueCare(Request $request)
    {
        $this->authorize('viewAny', Plant::class);

        $userId = $request->user()->id;
        $today = now()->toDateString();

        $plants = Plant::where('user_id', $userId)
            ->with(['careSettings', 'room'])
            ->get();

        $overdueTasks = [];

        foreach ($plants as $plant) {
            $tasksForToday = $this->getTasksForDate($plant, $today);

            foreach ($tasksForToday as $task) {
                if ($task['is_overdue']) {
                    $overdueTasks[] = [
                        'id' => $task['id'],
                        'plant_id' => $plant->id,
                        'plant_name' => $plant->name,
                        'room_name' => $plant->room?->name,
                        'type' => $task['type'],
                        'due_date' => $task['next_due_date'],
                        'overdue_days' => $task['overdue_days'],
                        'priority' => $task['overdue_days'] > 7 ? 'high' : ($task['overdue_days'] > 3 ? 'medium' : 'low'),
                    ];
                }
            }
        }

        usort($overdueTasks, fn ($a, $b) => $b['overdue_days'] <=> $a['overdue_days']);

        return response()->json([
            'date' => $today,
            'total' => count($overdueTasks),
            'tasks' => $overdueTasks,
        ]);
    }

    private function getTasksForDate(Plant $plant, $dateStr): array
    {
        $date = Carbon::parse($dateStr)->toDateString();
        $tasks = [];

        foreach ($plant->careSettings as $setting) {
            if (! $setting->is_enabled) {
                continue;
            }

            $intervalDays = (int) $setting->interval_days;
            $baseDate = $setting->last_done_at ?? $plant->planted_at;
            $nextDueDate = $baseDate->copy()->addDays($intervalDays);

            if ($nextDueDate->toDateString() <= $date) {
                $overdueDays = $nextDueDate->diffInDays(Carbon::parse($date), false);

                $tasks[] = [
                    'id' => $setting->id,
                    'type' => $setting->type,
                    'interval_days' => $intervalDays,
                    'last_done_at' => $setting->last_done_at?->toISOString(),
                    'next_due_date' => $nextDueDate->toDateString(),
                    'is_overdue' => $overdueDays > 0,
                    'overdue_days' => max(0, $overdueDays),
                ];
            }
        }

        return $tasks;
    }

    private function generateSchedule(Plant $plant, Carbon $startDate, Carbon $endDate): array
    {
        $schedule = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $tasksForDate = $this->getTasksForDate($plant, $dateStr);

            $schedule[] = [
                'date' => $dateStr,
                'tasks' => $tasksForDate,
            ];

            $currentDate->addDay();
        }

        return $schedule;
    }
}
