<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
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
        return new ReportResource(Report::with(['reporter', 'reviewer'])->findOrFail($id));
    }

    public function review(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
            'admin_comment' => 'nullable|string|max:1000',
        ]);

        $report = DB::transaction(function () use ($request, $id, $validated) {
            $report = Report::lockForUpdate()->findOrFail($id);
            $wasAccepted = $report->status === 'accepted';

            $report->update([
                'status' => $validated['status'],
                'admin_comment' => $validated['admin_comment'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // A confirmed complaint about a tip penalizes the author once.
            if ($report->target_type === Report::TARGET_TIP && ! $wasAccepted && $validated['status'] === 'accepted') {
                $tip = Tip::with('author')->find($report->target_id);
                $tip?->author?->decrement('rank');
            }

            return $report;
        });

        return new ReportResource($report->load(['reporter', 'reviewer']));
    }
}
