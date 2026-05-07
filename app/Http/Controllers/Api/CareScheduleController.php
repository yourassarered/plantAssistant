<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\CareSetting;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CareScheduleController extends Controller
{
    /**
     * Расписание ухода для конкретного растения
     */
    public function plantSchedule(Request $request, $plantId)
    {
        $this->authorizePlantAccess($request->user()->id, $plantId);

        $plant = Plant::with('careSettings')->findOrFail($plantId);

        // Получаем даты диапазона
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
            'total_tasks' => collect($schedule)->sum(fn($day) => count($day['tasks'])),
        ]);
    }

    /**
     * Все растения, которым нужен уход на сегодняшний день
     */
    public function todaysCare(Request $request)
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();

        $plants = Plant::where('user_id', $userId)
            ->with(['careSettings', 'room'])
            ->get();

        $plantsNeedingCare = [];
        $totalTasks = 0;

        foreach ($plants as $plant) {
            $tasksForPlant = $this->getTasksForDate($plant, $today);

            if (!empty($tasksForPlant)) {
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

        // Сортируем по статусу "просроченный"
        usort($plantsNeedingCare, function ($a, $b) {
            $aOverdue = collect($a['tasks'])->filter(fn($t) => $t['is_overdue'])->count();
            $bOverdue = collect($b['tasks'])->filter(fn($t) => $t['is_overdue'])->count();
            return $bOverdue <=> $aOverdue;
        });

        return response()->json([
            'date' => $today,
            'plants_count' => count($plantsNeedingCare),
            'total_tasks' => $totalTasks,
            'plants' => $plantsNeedingCare,
        ]);
    }

    /**
     * Расписание ухода на месяц
     */
    public function monthSchedule(Request $request)
    {
        $userId = $request->user()->id;

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        $plants = Plant::where('user_id', $userId)
            ->with('careSettings')
            ->get();

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

            // Для каждого дня месяца
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dateStr = $currentDate->toDateString();

                $tasksForDate = $this->getTasksForDate($plant, $dateStr);

                if (!empty($tasksForDate)) {
                    if (!isset($schedule[$dateStr])) {
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

        // Форматируем расписание в массив по дням
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

   /**
 * Предстоящие задачи на N дней вперед
 */
public function upcomingCare(Request $request)
{
    $userId = $request->user()->id;
    $days = (int) $request->get('days', 7); // Явное приведение к int

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

            if (!empty($tasksForDate)) {
                if (!isset($schedule[$dateStr])) {
                    $schedule[$dateStr] = [
                        'date' => $dateStr,
                        'tasks' => [],
                    ];
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

    // Сортируем по датам
    ksort($schedule);

    return response()->json([
        'period_days' => $days,
        'start_date' => $startDate->toDateString(),
        'end_date' => $endDate->toDateString(),
        'tasks' => array_values($schedule),
        'total_tasks' => collect($schedule)->sum(fn($day) => count($day['tasks'])),
    ]);
}

    /**
     * Просроченные задачи
     */
    public function overdueCare(Request $request)
    {
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

        // Сортируем по количеству просроченных дней (убывание)
        usort($overdueTasks, fn($a, $b) => $b['overdue_days'] <=> $a['overdue_days']);

        return response()->json([
            'date' => $today,
            'total' => count($overdueTasks),
            'tasks' => $overdueTasks,
        ]);
    }

   /**
 * Вспомогательный метод: получить задачи для конкретного растения на конкретную дату
 */
private function getTasksForDate(Plant $plant, $dateStr)
{
    $date = Carbon::parse($dateStr)->toDateString();
    $tasks = [];

    foreach ($plant->careSettings as $setting) {
        if (!$setting->is_enabled) {
            continue;
        }

        // Приводим interval_days к int
        $intervalDays = (int) $setting->interval_days;

        // Вычисляем дату последнего выполнения
        $baseDate = $setting->last_done_at ?? $plant->planted_at;

        // Вычисляем следующую дату выполнения
        $nextDueDate = $baseDate->copy()->addDays($intervalDays);

        // Проверяем, нужно ли выполнить уход в эту дату
        if ($nextDueDate->toDateString() <= $date) {
            $overdueDays = Carbon::parse($date)->diffInDays($nextDueDate, false);

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

  /**
 * Вспомогательный метод: генерация расписания для растения на период
 */
private function generateSchedule(Plant $plant, Carbon $startDate, Carbon $endDate)
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
    /**
     * Проверка доступа к растению
     */
    private function authorizePlantAccess($userId, $plantId)
    {
        $plant = Plant::where('user_id', $userId)->find($plantId);

        if (!$plant) {
            abort(403, 'Plant not found or does not belong to you');
        }
    }
}