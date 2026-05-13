<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Plant;
use App\Models\Report;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function reportPlant(Request $request, int $plantId)
    {
        $plant = Plant::findOrFail($plantId);

        if ($plant->user_id === $request->user()->id) {
            return response()->json(['message' => 'You cannot report your own plant'], 422);
        }

        return $this->createReport($request, Report::TARGET_PLANT, $plant->id);
    }

    public function reportTip(Request $request, int $tipId)
    {
        $tip = Tip::findOrFail($tipId);

        if ($tip->author_id === $request->user()->id) {
            return response()->json(['message' => 'You cannot report your own tip'], 422);
        }

        return $this->createReport($request, Report::TARGET_TIP, $tip->id);
    }

    private function createReport(Request $request, string $targetType, int $targetId)
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::in(['inappropriate_image', 'spam', 'abuse', 'misinformation', 'other'])],
            'details' => 'nullable|string|max:1000',
        ]);

        $report = Report::firstOrCreate(
            [
                'reporter_id' => $request->user()->id,
                'target_type' => $targetType,
                'target_id' => $targetId,
            ],
            [
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
            ]
        );

        if (! $report->wasRecentlyCreated) {
            return response()->json(['message' => 'You have already reported this item'], 422);
        }

        return (new ReportResource($report->load('reporter')))->response()->setStatusCode(201);
    }
}
