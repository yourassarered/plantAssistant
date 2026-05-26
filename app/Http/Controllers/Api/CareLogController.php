<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CareLogResource;
use App\Http\Resources\CareSettingResource;
use App\Models\CareLog;
use App\Models\CareSetting;
use App\Models\Plant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CareLogController extends Controller
{
    public function index(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('update', $plant);

        $logs = CareLog::where('plant_id', $plantId)
            ->orderBy('performed_at', 'desc')
            ->paginate(min($request->integer('per_page', 15), 100));

        return CareLogResource::collection($logs);
    }

    public function store(Request $request, $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('create', [CareLog::class, $plant->user_id]);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['watering', 'fertilizing', 'pruning', 'rotation'])],
            'performed_at' => 'nullable|date',
            'comment' => 'nullable|string|max:1000',
        ]);

        $performedAt = isset($validated['performed_at'])
            ? Carbon::parse($validated['performed_at'])
            : now();

        [$log, $setting] = DB::transaction(function () use ($plantId, $validated, $performedAt) {
            $log = CareLog::create([
                'plant_id' => $plantId,
                'type' => $validated['type'],
                'performed_at' => $performedAt,
                'comment' => $validated['comment'] ?? null,
            ]);

            $setting = CareSetting::where('plant_id', $plantId)
                ->where('type', $validated['type'])
                ->first();

            if ($setting && (! $setting->last_done_at || $performedAt->greaterThan($setting->last_done_at))) {
                $setting->update(['last_done_at' => $performedAt]);
            }

            return [$log, $setting?->fresh('plant')];
        });

        $response = (new CareLogResource($log))->resolve($request);

        if ($setting) {
            $response['care_setting'] = (new CareSettingResource($setting))->resolve($request);
        }

        return response()->json(['data' => $response], 201);
    }

    public function show(Request $request, $id)
    {
        $log = CareLog::with('plant')->findOrFail($id);
        $this->authorize('view', $log);

        return new CareLogResource($log);
    }

    public function destroy(Request $request, $id)
    {
        $log = CareLog::with('plant')->findOrFail($id);
        $this->authorize('delete', $log);

        $log->delete();

        return response()->json([
            'message' => 'Care log deleted successfully',
        ]);
    }
}
