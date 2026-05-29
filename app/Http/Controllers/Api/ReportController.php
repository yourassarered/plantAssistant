<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Plant;
use App\Models\Report;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

    public function myReports(Request $request)
    {
        $reports = Report::with(['reporter', 'reviewer'])
            ->where('reporter_id', $request->user()->id)
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 100));

        $this->hydrateTargets($reports->getCollection());

        return ReportResource::collection($reports);
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
            return response()->json(['message' => 'Вы уже отправляли жалобу на этот объект'], 422);
        }

        return (new ReportResource($report->load('reporter')))->response()->setStatusCode(201);
    }

    private function hydrateTargets(Collection $reports): void
    {
        $plantIds = $reports
            ->where('target_type', Report::TARGET_PLANT)
            ->pluck('target_id')
            ->filter()
            ->unique()
            ->values();

        $tipIds = $reports
            ->where('target_type', Report::TARGET_TIP)
            ->pluck('target_id')
            ->filter()
            ->unique()
            ->values();

        $plants = Plant::with('user.role')
            ->whereIn('id', $plantIds)
            ->get()
            ->keyBy('id');

        $tips = Tip::withTrashed()
            ->with(['author.role', 'plant.user.role'])
            ->whereIn('id', $tipIds)
            ->get()
            ->keyBy('id');

        foreach ($reports as $report) {
            if ($report->target_type === Report::TARGET_PLANT) {
                $report->setRelation('resolvedPlant', $plants->get($report->target_id));
                continue;
            }

            if ($report->target_type === Report::TARGET_TIP) {
                $report->setRelation('resolvedTip', $tips->get($report->target_id));
            }
        }
    }
}
