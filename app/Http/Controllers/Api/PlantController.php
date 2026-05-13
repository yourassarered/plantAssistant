<?php

namespace App\Http\Controllers\Api;

use App\DTO\PlantFiltersData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlantIndexRequest;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PlantController extends Controller
{
    public function index(PlantIndexRequest $request)
    {
        $this->authorize('viewAny', Plant::class);

        $filters = PlantFiltersData::fromRequest($request);

        $query = Plant::where('user_id', $request->user()->id)
            ->with(['room', 'latestImage', 'careSettings', 'careLogs']);

        if ($filters->roomId) {
            $query->where('room_id', $filters->roomId);
        }

        if ($filters->isPublic !== null) {
            $query->where('is_public', $filters->isPublic);
        }

        if ($filters->search) {
            $query->where('name', 'ilike', "%{$filters->search}%");
        }

        $query->orderBy($filters->sortBy, $filters->sortOrder);

        return PlantResource::collection($query->paginate($filters->perPage));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Plant::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'planted_at' => 'required|date',
            'height' => 'nullable|numeric|min:0',
            'room_id' => 'nullable|exists:rooms,id',
            'is_public' => 'boolean',
        ]);

        if (! empty($validated['room_id'])) {
            $room = Room::findOrFail($validated['room_id']);
            $this->authorize('update', $room);
        }

        $plant = Plant::create([
            'name' => $validated['name'],
            'planted_at' => $validated['planted_at'],
            'height' => $validated['height'] ?? null,
            'room_id' => $validated['room_id'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
            'user_id' => $request->user()->id,
        ]);

        return new PlantResource($plant->load('latestImage'));
    }

    public function show(Request $request, $id)
    {
        $plant = Plant::with(['room', 'latestImage', 'careSettings', 'careLogs', 'tips', 'likes'])
            ->findOrFail($id);

        $this->authorize('view', $plant);

        return new PlantResource($plant);
    }

    public function update(Request $request, $id)
    {
        $plant = Plant::findOrFail($id);
        $this->authorize('update', $plant);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'planted_at' => 'sometimes|date',
            'height' => 'nullable|numeric|min:0',
            'room_id' => 'nullable|exists:rooms,id',
            'is_public' => 'sometimes|boolean',
        ]);

        if (! empty($validated['room_id'])) {
            $room = Room::findOrFail($validated['room_id']);
            $this->authorize('update', $room);
        }

        $plant->update($validated);

        return new PlantResource($plant->fresh(['room', 'latestImage']));
    }

    public function destroy(Request $request, $id)
    {
        $plant = Plant::findOrFail($id);
        $this->authorize('delete', $plant);

        $plant->delete();

        return response()->json([
            'message' => 'Plant deleted successfully',
        ]);
    }

    public function public(PlantIndexRequest $request)
    {
        $filters = PlantFiltersData::fromRequest($request);

        $query = Plant::where('is_public', true)
            ->with(['user.role', 'room', 'latestImage', 'likes'])
            ->withCount('likes');

        if ($filters->search) {
            $query->where('name', 'ilike', "%{$filters->search}%");
        }

        if ($request->get('sort_by') === 'likes') {
            $query->orderBy('likes_count', 'desc');
        } else {
            $query->orderBy($filters->sortBy, $filters->sortOrder);
        }

        return PlantResource::collection($query->paginate($filters->perPage));
    }

    public function byRoom(Request $request, $roomId)
    {
        $room = Room::findOrFail($roomId);
        $this->authorize('view', $room);

        $plants = Plant::where('room_id', $roomId)
            ->with(['latestImage', 'careSettings', 'careLogs'])
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 15), 100));

        return PlantResource::collection($plants);
    }

    public function togglePublic(Request $request, $id)
    {
        $plant = Plant::findOrFail($id);
        $this->authorize('update', $plant);

        $plant->is_public = ! $plant->is_public;
        $plant->save();

        return new PlantResource($plant->fresh('latestImage'));
    }

    public function todaysCare(Request $request)
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();

        $plants = Plant::where('user_id', $userId)
            ->with(['careSettings', 'room'])
            ->get();

        $plantsNeedingCare = [];

        foreach ($plants as $plant) {
            $tasksForPlant = [];

            foreach ($plant->careSettings as $setting) {
                if (! $setting->is_enabled) {
                    continue;
                }

                $intervalDays = (int) $setting->interval_days;
                $nextDueDate = $setting->last_done_at
                    ? $setting->last_done_at->copy()->addDays($intervalDays)->toDateString()
                    : $plant->planted_at->copy()->addDays($intervalDays)->toDateString();

                if ($nextDueDate <= $today) {
                    $overdueDays = Carbon::parse($nextDueDate)->diffInDays(now(), false);

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

            if (! empty($tasksForPlant)) {
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
            'total_tasks' => collect($plantsNeedingCare)->sum(fn ($item) => count($item['tasks'])),
        ]);
    }

    public function schedule(Request $request, $id)
    {
        $plant = Plant::with('careSettings')->findOrFail($id);
        $this->authorize('update', $plant);

        $startDate = $request->has('start_date')
            ? Carbon::parse($request->start_date)->toDateString()
            : now()->startOfMonth()->toDateString();

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)->toDateString()
            : now()->endOfMonth()->toDateString();

        $schedule = [];

        foreach ($plant->careSettings as $setting) {
            if (! $setting->is_enabled) {
                continue;
            }

            $intervalDays = (int) $setting->interval_days;
            $currentDate = $setting->last_done_at
                ? $setting->last_done_at->copy()->addDays($intervalDays)
                : $plant->planted_at->copy()->addDays($intervalDays);

            while ($currentDate->toDateString() <= $endDate) {
                if ($currentDate->toDateString() >= $startDate) {
                    $dateStr = $currentDate->toDateString();

                    if (! isset($schedule[$dateStr])) {
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
}
