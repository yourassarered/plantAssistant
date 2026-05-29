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
use App\Services\UserSanctionService;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly ModeratorAuditLogger $audit,
        private readonly UserSanctionService $sanctions,
    ) {}

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

            if ($report->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Жалоба уже рассмотрена.'],
                ]);
            }

            $resolution = null;
            $resolutionAction = $data->resolutionAction;
            if ($data->status === 'accepted') {
                $resolutionAction ??= $report->target_type === Report::TARGET_TIP
                    ? 'tip_warn_rank'
                    : 'warn_user';
                $resolution = $this->applyResolution($report, $resolutionAction, $data->adminComment);
            }

            $report->update([
                'status' => $data->status,
                'admin_comment' => $data->adminComment,
                'resolution_action' => $data->status === 'accepted' ? $resolutionAction : null,
                'resolution_summary' => $resolution,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

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
                'resolution_action' => $data->resolutionAction,
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

    private function applyResolution(Report $report, ?string $action, ?string $comment): string
    {
        $allowedActions = $report->target_type === Report::TARGET_TIP
            ? ['tip_delete_rank', 'block_user', 'tip_warn_rank']
            : ['hide_plant', 'block_user', 'warn_user'];

        if (! $action || ! in_array($action, $allowedActions, true)) {
            throw ValidationException::withMessages([
                'resolution_action' => ['Выберите решение для принятой жалобы.'],
            ]);
        }

        return $report->target_type === Report::TARGET_TIP
            ? $this->applyTipResolution($report, $action, $comment)
            : $this->applyPlantResolution($report, $action, $comment);
    }

    private function applyTipResolution(Report $report, string $action, ?string $comment): string
    {
        $tip = Tip::withTrashed()->with('author')->lockForUpdate()->findOrFail($report->target_id);
        $author = $tip->author;

        if (! $author) {
            return 'Совет обработан, автор не найден.';
        }

        if ($action === 'block_user') {
            $this->sanctions->block($author, $comment ?: 'Блокировка по принятой жалобе на совет.');

            return 'Автор совета заблокирован, активные токены и сессии сброшены.';
        }

        $author->decrement('rank');

        if ($action === 'tip_delete_rank') {
            $tip->delete();

            return 'Ранг автора снижен на 1, совет удален.';
        }

        $warning = $this->sanctions->warn($author, $comment ?: 'Предупреждение по принятой жалобе на совет.');

        return $warning['blocked']
            ? 'Ранг автора снижен на 1, выдано третье предупреждение, аккаунт автоматически заблокирован.'
            : "Ранг автора снижен на 1, предупреждение выдано ({$warning['warnings_count']}/3).";
    }

    private function applyPlantResolution(Report $report, string $action, ?string $comment): string
    {
        $plant = Plant::with('user')->lockForUpdate()->findOrFail($report->target_id);
        $owner = $plant->user;

        if ($action === 'hide_plant') {
            $plant->update([
                'is_public' => false,
                'public_hidden_at' => now(),
                'public_hidden_by' => request()->user()?->id,
                'public_hidden_reason' => $comment ?: 'Скрыто модератором по принятой жалобе.',
                'is_public_locked' => true,
            ]);

            return 'Растение скрыто из публичной ленты. Повторная публикация владельцем заблокирована.';
        }

        if (! $owner) {
            return 'Жалоба обработана, владелец растения не найден.';
        }

        if ($action === 'block_user') {
            $this->sanctions->block($owner, $comment ?: 'Блокировка по принятой жалобе на растение.');

            return 'Владелец растения заблокирован, активные токены и сессии сброшены.';
        }

        $warning = $this->sanctions->warn($owner, $comment ?: 'Предупреждение по принятой жалобе на растение.');

        return $warning['blocked']
            ? 'Выдано третье предупреждение, аккаунт владельца автоматически заблокирован.'
            : "Предупреждение владельцу выдано ({$warning['warnings_count']}/3).";
    }
}
