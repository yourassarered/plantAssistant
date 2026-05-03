<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CareSettingResource;
use App\Models\CareSetting;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CareSettingController extends Controller
{
    /**
     * Все настройки ухода для конкретного растения
     */
    public function index(Request $request, $plantId)
    {
        $this->authorizePlantAccess($request->user()->id, $plantId);

        $settings = CareSetting::where('plant_id', $plantId)->get();

        return CareSettingResource::collection($settings);
    }

    /**
     * Создание настройки ухода
     */
    public function store(Request $request, $plantId)
    {
        $this->authorizePlantAccess($request->user()->id, $plantId);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['watering', 'fertilizing', 'pruning', 'rotation'])],
            'interval_days' => 'required|integer|min:1',
            'is_enabled' => 'boolean',
        ]);

        // Проверяем, нет ли уже настройки такого типа для этого растения
        $existingSetting = CareSetting::where('plant_id', $plantId)
            ->where('type', $validated['type'])
            ->first();

        if ($existingSetting) {
            return response()->json([
                'message' => 'Care setting for this type already exists. Please update it instead.',
            ], 422);
        }

        $setting = CareSetting::create([
            'plant_id' => $plantId,
            'type' => $validated['type'],
            'interval_days' => $validated['interval_days'],
            'is_enabled' => $validated['is_enabled'] ?? true,
        ]);

        return new CareSettingResource($setting);
    }

    /**
     * Обновление настройки ухода
     */
    public function update(Request $request, $id)
    {
        $setting = CareSetting::findOrFail($id);
        $this->authorizePlantAccess($request->user()->id, $setting->plant_id);

        $validated = $request->validate([
            'interval_days' => 'sometimes|integer|min:1',
            'is_enabled' => 'sometimes|boolean',
        ]);

        $setting->update($validated);

        return new CareSettingResource($setting);
    }

    /**
     * Включение/отключение настройки
     */
    public function toggle(Request $request, $id)
    {
        $setting = CareSetting::findOrFail($id);
        $this->authorizePlantAccess($request->user()->id, $setting->plant_id);

        $setting->is_enabled = !$setting->is_enabled;
        $setting->save();

        return new CareSettingResource($setting);
    }

    /**
     * Удаление настройки ухода
     */
    public function destroy(Request $request, $id)
    {
        $setting = CareSetting::findOrFail($id);
        $this->authorizePlantAccess($request->user()->id, $setting->plant_id);

        $setting->delete();

        return response()->json([
            'message' => 'Care setting deleted successfully',
        ]);
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