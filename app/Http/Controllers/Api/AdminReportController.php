<?php

namespace App\Http\Controllers\Api;

use App\DTO\ReportReviewData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReviewReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\Tip;
use App\Services\ModeratorAuditLogger;
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

        return ReportResource::collection($query->paginate(min($request->integer('per_page', 15), 100)));
    }

    public function show(int $id)
    {
        $report = Report::with(['reporter', 'reviewer'])->findOrFail($id);
        $this->authorize('view', $report);

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
            ],
            request: $request
        );

        return new ReportResource($report->load(['reporter', 'reviewer']));
    }
}
