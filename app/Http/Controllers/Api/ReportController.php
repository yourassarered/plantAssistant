<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Plant;
use App\Models\Report;
use App\Models\Tip;

class ReportController extends Controller
{
    public function reportPlant(StoreReportRequest $request, int $plantId)
    {
        $plant = Plant::findOrFail($plantId);
        $this->authorize('createForPlant', [Report::class, $plant]);

        return $this->createReport($request, Report::TARGET_PLANT, $plant->id);
    }

    public function reportTip(StoreReportRequest $request, int $tipId)
    {
        $tip = Tip::findOrFail($tipId);
        $this->authorize('createForTip', [Report::class, $tip]);

        return $this->createReport($request, Report::TARGET_TIP, $tip->id);
    }

    private function createReport(StoreReportRequest $request, string $targetType, int $targetId)
    {
        $report = Report::firstOrCreate(
            [
                'reporter_id' => $request->user()->id,
                'target_type' => $targetType,
                'target_id' => $targetId,
            ],
            [
                'reason' => $request->string('reason')->value(),
                'details' => $request->input('details'),
            ]
        );

        if (! $report->wasRecentlyCreated) {
            return response()->json(['message' => 'You have already reported this item'], 422);
        }

        return (new ReportResource($report->load('reporter')))->response()->setStatusCode(201);
    }
}
