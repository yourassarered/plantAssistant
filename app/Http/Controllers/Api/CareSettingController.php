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
    public function index(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('update', $plant);

        return CareSettingResource::collection(
            CareSetting::where('plant_id', $plantId)->get()
        );
    }

    public function store(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('create', [CareSetting::class, $plant->user_id]);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['watering', 'fertilizing', 'pruning', 'rotation'])],
            'interval_days' => 'required|integer|min:1',
            'is_enabled' => 'boolean',
        ]);

        $exists = CareSetting::where('plant_id', $plantId)
            ->where('type', $validated['type'])
            ->exists();

        if ($exists) {
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

    public function update(Request $request, $id)
    {
        $setting = CareSetting::with('plant')->findOrFail($id);
        $this->authorize('update', $setting);

        $validated = $request->validate([
            'interval_days' => 'sometimes|integer|min:1',
            'is_enabled' => 'sometimes|boolean',
        ]);

        $setting->update($validated);

        return new CareSettingResource($setting);
    }

    public function toggle(Request $request, $id)
    {
        $setting = CareSetting::with('plant')->findOrFail($id);
        $this->authorize('update', $setting);

        $setting->is_enabled = ! $setting->is_enabled;
        $setting->save();

        return new CareSettingResource($setting);
    }

    public function destroy(Request $request, $id)
    {
        $setting = CareSetting::with('plant')->findOrFail($id);
        $this->authorize('delete', $setting);

        $setting->delete();

        return response()->json([
            'message' => 'Настройка ухода удалена',
        ]);
    }
}
