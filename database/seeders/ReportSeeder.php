<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\Report;
use App\Models\Role;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('role', fn ($query) => $query->where('name', 'admin'))->first();
        $userRoleId = Role::where('name', 'user')->firstOrFail()->id;
        $users = User::where('role_id', $userRoleId)->orderBy('id')->get();
        $publicPlants = Plant::with('user')->where('is_public', true)->orderBy('id')->get();
        $tips = Tip::withTrashed()->with(['author', 'plant.user'])->orderBy('id')->get();

        if (! $admin || $users->count() < 3 || $publicPlants->count() < 4 || $tips->count() < 4) {
            return;
        }

        $plantScenarios = [
            [
                'plant' => $publicPlants[0],
                'reason' => 'inappropriate_image',
                'status' => 'pending',
                'details' => 'На фото плохо видно растение, больше похоже на случайный предмет.',
                'admin_comment' => null,
                'resolution_action' => null,
                'resolution_summary' => null,
            ],
            [
                'plant' => $publicPlants[1],
                'reason' => 'spam',
                'status' => 'accepted',
                'details' => 'Похоже на рекламную публикацию без пользы для сообщества.',
                'admin_comment' => 'Подтвердили спам, карточка скрыта из публичной ленты.',
                'resolution_action' => 'hide_plant',
                'resolution_summary' => 'Растение скрыто из публичной ленты и повторная публикация заблокирована.',
            ],
            [
                'plant' => $publicPlants[2],
                'reason' => 'other',
                'status' => 'rejected',
                'details' => 'Пожаловался на оформление карточки, но нарушения правил не нашлось.',
                'admin_comment' => 'Нарушений не обнаружено, жалоба отклонена.',
                'resolution_action' => null,
                'resolution_summary' => 'Нарушений не найдено, санкции не применялись.',
            ],
            [
                'plant' => $publicPlants[3],
                'reason' => 'misinformation',
                'status' => 'accepted',
                'details' => 'Описание и фотографии вводят в заблуждение, владелец несколько раз публиковал одинаковый контент.',
                'admin_comment' => 'Провели проверку и вынесли владельцу предупреждение.',
                'resolution_action' => 'warn_user',
                'resolution_summary' => 'Владельцу выдано предупреждение, повторное нарушение приведет к блокировке.',
            ],
        ];

        foreach ($plantScenarios as $index => $scenario) {
            $plant = $scenario['plant'];
            $reporter = $this->pickReporter($users, [$plant->user_id], $index);
            if (! $reporter) {
                continue;
            }

            $this->seedReport(
                admin: $admin,
                reporter: $reporter,
                targetType: Report::TARGET_PLANT,
                targetId: $plant->id,
                reason: $scenario['reason'],
                details: $scenario['details'],
                status: $scenario['status'],
                adminComment: $scenario['admin_comment'],
                resolutionAction: $scenario['resolution_action'],
                resolutionSummary: $scenario['resolution_summary'],
            );
        }

        $tipScenarios = [
            [
                'tip' => $tips[0],
                'reason' => 'abuse',
                'status' => 'pending',
                'details' => 'Совет написан в резком тоне и выглядит провокационно.',
                'admin_comment' => null,
                'resolution_action' => null,
                'resolution_summary' => null,
            ],
            [
                'tip' => $tips[1],
                'reason' => 'misinformation',
                'status' => 'accepted',
                'details' => 'Совет рекомендует слишком частый полив и может навредить растению.',
                'admin_comment' => 'Совет удален, автору понижен ранг.',
                'resolution_action' => 'tip_delete_rank',
                'resolution_summary' => 'Совет удален, ранг автора снижен на 1.',
            ],
            [
                'tip' => $tips[2],
                'reason' => 'spam',
                'status' => 'accepted',
                'details' => 'В совете есть навязчивая реклама удобрений и ссылок на магазины.',
                'admin_comment' => 'Автору вынесено предупреждение и снижен ранг.',
                'resolution_action' => 'tip_warn_rank',
                'resolution_summary' => 'Автор получил предупреждение, ранг снижен на 1.',
            ],
            [
                'tip' => $tips[3],
                'reason' => 'other',
                'status' => 'rejected',
                'details' => 'Жалоба связана скорее со вкусом, а не с нарушением правил.',
                'admin_comment' => 'Оснований для санкций нет.',
                'resolution_action' => null,
                'resolution_summary' => 'Жалоба отклонена после ручной проверки.',
            ],
        ];

        foreach ($tipScenarios as $index => $scenario) {
            $tip = $scenario['tip'];
            $excludedIds = array_filter([
                $tip->author_id,
                $tip->plant?->user_id,
            ]);
            $reporter = $this->pickReporter($users, $excludedIds, $index + count($plantScenarios));
            if (! $reporter) {
                continue;
            }

            $this->seedReport(
                admin: $admin,
                reporter: $reporter,
                targetType: Report::TARGET_TIP,
                targetId: $tip->id,
                reason: $scenario['reason'],
                details: $scenario['details'],
                status: $scenario['status'],
                adminComment: $scenario['admin_comment'],
                resolutionAction: $scenario['resolution_action'],
                resolutionSummary: $scenario['resolution_summary'],
            );
        }
    }

    private function pickReporter($users, array $excludedIds, int $offset): ?User
    {
        $availableUsers = $users
            ->whereNotIn('id', $excludedIds)
            ->values();

        if ($availableUsers->isEmpty()) {
            return null;
        }

        return $availableUsers[$offset % $availableUsers->count()];
    }

    private function seedReport(
        User $admin,
        User $reporter,
        string $targetType,
        int $targetId,
        string $reason,
        string $details,
        string $status,
        ?string $adminComment,
        ?string $resolutionAction,
        ?string $resolutionSummary,
    ): void {
        $report = Report::firstOrNew([
            'reporter_id' => $reporter->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);

        $wasAccepted = $report->exists && $report->status === 'accepted';
        $reviewedAt = $status === 'pending' ? null : now()->subDays(1 + ($targetId % 14));

        $report->fill([
            'reason' => $reason,
            'details' => $details,
            'status' => $status,
            'admin_comment' => $adminComment,
            'resolution_action' => $status === 'accepted' ? $resolutionAction : null,
            'resolution_summary' => $status === 'accepted' || $status === 'rejected' ? $resolutionSummary : null,
            'reviewed_by' => $status === 'pending' ? null : $admin->id,
            'reviewed_at' => $reviewedAt,
        ]);
        $report->save();

        if ($status === 'accepted' && ! $wasAccepted) {
            $this->applyAcceptedResolution($report, $resolutionAction, $admin);
        }
    }

    private function applyAcceptedResolution(Report $report, ?string $resolutionAction, User $admin): void
    {
        if ($report->target_type === Report::TARGET_PLANT) {
            $plant = Plant::with('user')->find($report->target_id);
            if (! $plant) {
                return;
            }

            if ($resolutionAction === 'hide_plant') {
                $plant->update([
                    'is_public' => false,
                    'public_hidden_at' => $plant->public_hidden_at ?? now()->subDays(2),
                    'public_hidden_by' => $plant->public_hidden_by ?? $admin->id,
                    'public_hidden_reason' => $plant->public_hidden_reason ?: 'Скрыто модератором по подтвержденной жалобе.',
                    'is_public_locked' => true,
                ]);

                return;
            }

            $owner = $plant->user;
            if (! $owner) {
                return;
            }

            if ($resolutionAction === 'block_user') {
                $owner->update([
                    'warnings_count' => max((int) $owner->warnings_count, 3),
                    'blocked_at' => $owner->blocked_at ?? now()->subDays(2),
                    'block_reason' => $owner->block_reason ?: 'Аккаунт заблокирован по подтвержденной жалобе на растение.',
                ]);

                return;
            }

            if ($resolutionAction === 'warn_user') {
                $warningsCount = min(3, ((int) $owner->warnings_count) + 1);
                $owner->update([
                    'warnings_count' => $warningsCount,
                    'blocked_at' => $warningsCount >= 3 ? ($owner->blocked_at ?? now()->subDay()) : $owner->blocked_at,
                    'block_reason' => $warningsCount >= 3
                        ? ($owner->block_reason ?: 'Автоматическая блокировка после трех предупреждений.')
                        : $owner->block_reason,
                ]);
            }

            return;
        }

        $tip = Tip::withTrashed()->with('author')->find($report->target_id);
        if (! $tip) {
            return;
        }

        $author = $tip->author;
        if ($resolutionAction === 'tip_delete_rank') {
            if (! $tip->trashed()) {
                $tip->delete();
            }

            if ($author) {
                $author->update([
                    'rank' => max(0, (int) $author->rank - 1),
                ]);
            }

            return;
        }

        if (! $author) {
            return;
        }

        if ($resolutionAction === 'block_user') {
            $author->update([
                'warnings_count' => max((int) $author->warnings_count, 3),
                'blocked_at' => $author->blocked_at ?? now()->subDays(1),
                'block_reason' => $author->block_reason ?: 'Аккаунт заблокирован по подтвержденной жалобе на совет.',
            ]);

            return;
        }

        if ($resolutionAction === 'tip_warn_rank') {
            $warningsCount = min(3, ((int) $author->warnings_count) + 1);
            $author->update([
                'rank' => max(0, (int) $author->rank - 1),
                'warnings_count' => $warningsCount,
                'blocked_at' => $warningsCount >= 3 ? ($author->blocked_at ?? now()->subDay()) : $author->blocked_at,
                'block_reason' => $warningsCount >= 3
                    ? ($author->block_reason ?: 'Автоматическая блокировка после трех предупреждений.')
                    : $author->block_reason,
            ]);
        }
    }
}
