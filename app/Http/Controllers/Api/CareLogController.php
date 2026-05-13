<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CareLogResource;
use App\Models\CareLog;
use App\Models\CareSetting;
use App\Models\Plant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CareLogController extends Controller
{
    /**
     * История ухода для растения
     */
    public function index(Request $request, $plantId)
    {
        $this->authorizePlantAccess($request->user()->id, $plantId);

        $logs = CareLog::where('plant_id', $plantId)
            ->orderBy('performed_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return CareLogResource::collection($logs);
    }

    /**
     * Добавление записи о выполненном уходе
     */
    public function store(Request $request, $plantId)
    {
        $this->authorizePlantAccess($request->user()->id, $plantId);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['watering', 'fertilizing', 'pruning', 'rotation'])],
            'performed_at' => 'nullable|date',
            'comment' => 'nullable|string|max:1000',
        ]);

        $performedAt = isset($validated['performed_at'])
            ? Carbon::parse($validated['performed_at'])
            : now();

        // 1. Создаем запись в истории
        $log = CareLog::create([
            'plant_id' => $plantId,
            'type' => $validated['type'],
            'performed_at' => $performedAt,
            'comment' => $validated['comment'] ?? null,
        ]);

        // 2. Обновляем `last_done_at` в настройках ухода,
        // если дата выполнения новее, чем текущая last_done_at
        $setting = CareSetting::where('plant_id', $plantId)
            ->where('type', $validated['type'])
            ->first();

        if ($setting) {
            $currentLastDone = $setting->last_done_at;

            if (! $currentLastDone || $performedAt->greaterThan($currentLastDone)) {
                $setting->update(['last_done_at' => $performedAt]);
            }
        }

        return new CareLogResource($log);
    }

    /**
     * Просмотр конкретной записи
     */
    public function show(Request $request, $id)
    {
        $log = CareLog::findOrFail($id);
        $this->authorizePlantAccess($request->user()->id, $log->plant_id);

        return new CareLogResource($log);
    }

    /**
     * Удаление записи из истории
     */
    public function destroy(Request $request, $id)
    {
        $log = CareLog::findOrFail($id);
        $this->authorizePlantAccess($request->user()->id, $log->plant_id);

        $log->delete();

        return response()->json([
            'message' => 'Care log deleted successfully',
        ]);
    }

    /**
     * Проверка доступа к растению
     */
    private function authorizePlantAccess($userId, $plantId)
    {
        $plant = Plant::where('user_id', $userId)->find($plantId);

        if (! $plant) {
            abort(403, 'Plant not found or does not belong to you');
        }
    }
}
