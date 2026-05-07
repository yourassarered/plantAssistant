<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PlantController extends Controller
{
    /**
     * Список растений текущего пользователя
     */
    public function index(Request $request)
    {
        $query = Plant::where('user_id', $request->user()->id)
            ->with(['room', 'careSettings', 'careLogs']);

        // Фильтр по комнате
        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        // Фильтр по публичности
        if ($request->has('is_public')) {
            $query->where('is_public', $request->is_public);
        }

        // Поиск по названию
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%");
        }

        // Сортировка
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $plants = $query->paginate($request->get('per_page', 15));

        return PlantResource::collection($plants);
    }

    /**
     * Создание растения
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'planted_at' => 'required|date',
            'height' => 'nullable|numeric|min:0',
            'room_id' => 'nullable|exists:rooms,id',
            'is_public' => 'boolean',
        ]);

        // Проверяем, что комната принадлежит пользователю
        if ($request->has('room_id') && $request->room_id) {
            $this->validateRoomOwnership($request->user()->id, $request->room_id);
        }

        $plant = Plant::create([
            'name' => $validated['name'],
            'planted_at' => $validated['planted_at'],
            'height' => $validated['height'] ?? null,
            'room_id' => $validated['room_id'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
            'user_id' => $request->user()->id,
        ]);

        return new PlantResource($plant);
    }

    /**
     * Просмотр детальной информации о растении
     */
    public function show(Request $request, $id)
    {
        $plant = Plant::with(['room', 'careSettings', 'careLogs', 'tips', 'likes'])
            ->findOrFail($id);

        // Проверка доступа: может просмотреть свои растения или публичные
        if ($plant->user_id !== $request->user()->id && !$plant->is_public) {
            abort(403, 'Unauthorized');
        }

        return new PlantResource($plant);
    }

    /**
     * Редактирование растения
     */
    public function update(Request $request, $id)
    {
        $plant = Plant::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'planted_at' => 'sometimes|date',
            'height' => 'nullable|numeric|min:0',
            'room_id' => 'nullable|exists:rooms,id',
            'is_public' => 'sometimes|boolean',
        ]);

        // Проверяем, что комната принадлежит пользователю
        if ($request->has('room_id') && $request->room_id) {
            $this->validateRoomOwnership($request->user()->id, $request->room_id);
        }

        $plant->update($validated);

        return new PlantResource($plant);
    }

    /**
     * Удаление растения
     */
    public function destroy(Request $request, $id)
    {
        $plant = Plant::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $plant->delete();

        return response()->json([
            'message' => 'Plant deleted successfully',
        ]);
    }

    /**
     * Список публичных растений (для ленты)
     */
    public function public(Request $request)
    {
        $query = Plant::where('is_public', true)
            ->with(['user', 'room', 'likes'])
            ->withCount('likes');

        // Поиск по названию
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%");
        }

        // Сортировка по популярности (количество лайков)
        if ($request->get('sort_by') === 'likes') {
            $query->orderBy('likes_count', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $plants = $query->paginate($request->get('per_page', 15));

        return PlantResource::collection($plants);
    }

    /**
     * Растения конкретной комнаты
     */
    public function byRoom(Request $request, $roomId)
    {
        // Проверяем принадлежность комнаты
        $this->validateRoomOwnership($request->user()->id, $roomId);

        $plants = Plant::where('user_id', $request->user()->id)
            ->where('room_id', $roomId)
            ->with(['careSettings', 'careLogs'])
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return PlantResource::collection($plants);
    }

    /**
     * Переключение статуса публичности
     */
    public function togglePublic(Request $request, $id)
    {
        $plant = Plant::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $plant->is_public = !$plant->is_public;
        $plant->save();

        return new PlantResource($plant);
    }

    /**
 * Все растения, которым нужен уход на сегодняшний день
 */
public function todaysCare(Request $request)
{
    $userId = $request->user()->id;
    $today = now()->toDateString();

    // Получаем все растения пользователя с настройками ухода
    $plants = Plant::where('user_id', $userId)
        ->with(['careSettings', 'room'])
        ->get();

    $plantsNeedingCare = [];

    foreach ($plants as $plant) {
        $tasksForPlant = [];

        foreach ($plant->careSettings as $setting) {
            // Пропускаем отключённые настройки
            if (!$setting->is_enabled) {
                continue;
            }

            // Приводим к int
            $intervalDays = (int) $setting->interval_days;

            // Вычисляем следующую дату выполнения
            $nextDueDate = $setting->last_done_at
                ? $setting->last_done_at->copy()->addDays($intervalDays)->toDateString()
                : $plant->planted_at->copy()->addDays($intervalDays)->toDateString();

            // Проверяем, нужно ли выполнить уход сегодня
            if ($nextDueDate <= $today) {
                $overdueDays = now()->diffInDays(Carbon::parse($nextDueDate), false);

                $tasksForPlant[] = [
                    'id' => $setting->id,
                    'type' => $setting->type,
                    'interval_days' => $intervalDays,
                    'last_done_at' => $setting->last_done_at?->toISOString(),
                    'next_due_date' => $nextDueDate,
                    'is_overdue' => $overdueDays > 0,
                    'overdue_days' => max(0, $overdueDays),
                ];
            }
        }

        // Если есть задачи для этого растения, добавляем его в результат
        if (!empty($tasksForPlant)) {
            $plantsNeedingCare[] = [
                'plant' => new PlantResource($plant),
                'tasks' => $tasksForPlant,
            ];
        }
    }

    return response()->json([
        'date' => $today,
        'plants' => $plantsNeedingCare,
        'total_plants' => count($plantsNeedingCare),
        'total_tasks' => collect($plantsNeedingCare)->sum(fn($item) => count($item['tasks'])),
    ]);
}

/**
 * Расписание ухода для конкретного растения
 */
public function schedule(Request $request, $id)
{
    $plant = Plant::where('user_id', $request->user()->id)
        ->with('careSettings')
        ->findOrFail($id);

    $startDate = $request->has('start_date') 
        ? Carbon::parse($request->start_date)->toDateString()
        : now()->startOfMonth()->toDateString();

    $endDate = $request->has('end_date')
        ? Carbon::parse($request->end_date)->toDateString()
        : now()->endOfMonth()->toDateString();

    $schedule = [];

    foreach ($plant->careSettings as $setting) {
        if (!$setting->is_enabled) {
            continue;
        }

        // Приводим к int
        $intervalDays = (int) $setting->interval_days;

        // Определяем первую дату
        $currentDate = $setting->last_done_at
            ? $setting->last_done_at->copy()->addDays($intervalDays)
            : $plant->planted_at->copy()->addDays($intervalDays);

        // Генерируем даты в диапазоне
        while ($currentDate->toDateString() <= $endDate) {
            if ($currentDate->toDateString() >= $startDate) {
                $dateStr = $currentDate->toDateString();

                if (!isset($schedule[$dateStr])) {
                    $schedule[$dateStr] = [];
                }

                $schedule[$dateStr][] = [
                    'id' => $setting->id,
                    'type' => $setting->type,
                    'interval_days' => $intervalDays,
                ];
            }

            $currentDate->addDays($intervalDays);
        }
    }

    // Форматируем вывод
    $formattedSchedule = [];
    foreach ($schedule as $date => $tasks) {
        $formattedSchedule[] = [
            'date' => $date,
            'tasks' => $tasks,
        ];
    }

    return response()->json([
        'plant_id' => $plant->id,
        'plant_name' => $plant->name,
        'period' => [
            'start' => $startDate,
            'end' => $endDate,
        ],
        'schedule' => $formattedSchedule,
    ]);
}

    /**
     * Проверка принадлежности комнаты пользователю
     */
    private function validateRoomOwnership($userId, $roomId)
    {
        $room = \App\Models\Room::where('user_id', $userId)
            ->where('id', $roomId)
            ->first();

        if (!$room) {
            abort(403, 'Room not found or does not belong to you');
        }
    }
}