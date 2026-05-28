<?php

namespace App\Http\Controllers\Api;

use App\DTO\ReportReviewData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReviewReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Plant;
use App\Models\Report;
use App\Models\Tip;
use App\Services\ModeratorAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function __construct(private readonly ModeratorAuditLogger $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Report::class);

        $query = Report::with(['reporter', 'reviewer'])->latest();

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('target_type')) {
            $query->where('target_type', $request->get('target_type'));
        }

        $reports = $query->paginate(min($request->integer('per_page', 15), 100));
        $this->hydrateTargets($reports->getCollection());

        return ReportResource::collection($reports);
    }

    public function show(int $id)
    {
        $report = Report::with(['reporter', 'reviewer'])->findOrFail($id);
        $this->authorize('view', $report);
        $this->hydrateTargets(collect([$report]));

        return new ReportResource($report);
    }

    public function review(ReviewReportRequest $request, int $id)
    {
        $data = ReportReviewData::fromRequest($request);

        $report = DB::transaction(function () use ($request, $id, $data) {
            $report = Report::lockForUpdate()->findOrFail($id);
            $this->authorize('review', $report);

            $wasAccepted = $report->status === 'accepted';

            $report->update([
                'status' => $data->status,
                'admin_comment' => $data->adminComment,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            if ($report->target_type === Report::TARGET_TIP && ! $wasAccepted && $data->status === 'accepted') {
                $tip = Tip::with('author')->find($report->target_id);
                $tip?->author?->decrement('rank');
            }

            return $report;
        });

        $this->audit->log(
            actor: $request->user(),
            action: 'report.review',
            targetType: Report::class,
            targetId: $report->id,
            payload: [
                'status' => $data->status,
                'admin_comment' => $data->adminComment,
                'report_target_type' => $report->target_type,
                'report_target_id' => $report->target_id,
            ],
            request: $request
        );

        $report->load(['reporter', 'reviewer']);
        $this->hydrateTargets(collect([$report]));

        return new ReportResource($report);
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

        $tips = Tip::with(['author.role', 'plant.user.role'])
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
