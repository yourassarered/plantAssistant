<?php

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'reason' => $this->reason,
            'reason_label' => $this->reasonLabel(),
            'details' => $this->details,
            'target' => $this->resolveTarget(),
            'moderation_effect' => $this->resolveModerationEffect(),
            'admin_comment' => $this->admin_comment,
            'resolution_action' => $this->resolution_action,
            'resolution_summary' => $this->resolution_summary,
            'reporter' => new UserResource($this->whenLoaded('reporter')),
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function resolveTarget(): ?array
    {
        if ($this->target_type === Report::TARGET_PLANT) {
            if (! $this->relationLoaded('resolvedPlant') || ! $this->resolvedPlant) {
                return null;
            }

            $plant = $this->resolvedPlant;

            return [
                'kind' => Report::TARGET_PLANT,
                'plant' => [
                    'id' => $plant->id,
                    'name' => $plant->name,
                    'owner_id' => $plant->user?->id,
                    'owner_name' => $plant->user?->name,
                    'owner_rank' => $plant->user?->rank,
                    'owner_warnings_count' => $plant->user?->warnings_count,
                    'owner_blocked_at' => $plant->user?->blocked_at?->toISOString(),
                    'is_public' => (bool) $plant->is_public,
                    'is_public_locked' => (bool) $plant->is_public_locked,
                    'public_hidden_at' => $plant->public_hidden_at?->toISOString(),
                ],
            ];
        }

        if ($this->target_type === Report::TARGET_TIP) {
            if (! $this->relationLoaded('resolvedTip') || ! $this->resolvedTip) {
                return null;
            }

            $tip = $this->resolvedTip;

            return [
                'kind' => Report::TARGET_TIP,
                'tip' => [
                    'id' => $tip->id,
                    'content' => $tip->content,
                    'status' => $tip->status,
                    'author_id' => $tip->author?->id,
                    'author_name' => $tip->author?->name,
                    'author_rank' => $tip->author?->rank,
                    'author_warnings_count' => $tip->author?->warnings_count,
                    'author_blocked_at' => $tip->author?->blocked_at?->toISOString(),
                    'deleted_at' => $tip->deleted_at?->toISOString(),
                ],
                'plant' => [
                    'id' => $tip->plant?->id,
                    'name' => $tip->plant?->name,
                    'owner_id' => $tip->plant?->user?->id,
                    'owner_name' => $tip->plant?->user?->name,
                    'owner_rank' => $tip->plant?->user?->rank,
                    'owner_warnings_count' => $tip->plant?->user?->warnings_count,
                    'owner_blocked_at' => $tip->plant?->user?->blocked_at?->toISOString(),
                    'is_public' => (bool) $tip->plant?->is_public,
                    'is_public_locked' => (bool) $tip->plant?->is_public_locked,
                ],
            ];
        }

        return null;
    }

    private function resolveModerationEffect(): array
    {
        if ($this->resolution_summary) {
            return [
                'code' => $this->resolution_action ?: 'custom_resolution',
                'summary' => $this->resolution_summary,
            ];
        }

        if ($this->target_type === Report::TARGET_TIP) {
            return match ($this->status) {
                'accepted' => [
                    'code' => 'tip_rank_penalty_applied',
                    'summary' => 'Жалоба принята: санкция к совету уже применена.',
                ],
                'rejected' => [
                    'code' => 'tip_no_penalty',
                    'summary' => 'Жалоба отклонена: санкции к автору совета не применялись.',
                ],
                default => [
                    'code' => 'tip_rank_penalty_pending',
                    'summary' => 'Если жалобу примут, к совету и его автору применят санкции.',
                ],
            };
        }

        return match ($this->status) {
            'accepted' => [
                'code' => 'plant_manual_review_applied',
                'summary' => 'Жалоба принята и рассмотрена модератором.',
            ],
            'rejected' => [
                'code' => 'plant_no_penalty',
                'summary' => 'Жалоба отклонена. Санкции к растению не применялись.',
            ],
            default => [
                'code' => 'plant_manual_review_pending',
                'summary' => 'Жалоба ожидает решения модератора.',
            ],
        };
    }

    private function statusLabel(): string
    {
        $status = $this->status ?: 'pending';

        return match ($status) {
            'pending' => 'На рассмотрении',
            'accepted' => 'Принята',
            'rejected' => 'Отклонена',
            default => (string) $status,
        };
    }

    private function reasonLabel(): string
    {
        $reason = $this->reason ?: 'other';

        return match ($reason) {
            'inappropriate_image' => 'Неподходящее изображение',
            'spam' => 'Спам',
            'abuse' => 'Оскорбления',
            'misinformation' => 'Недостоверная информация',
            'other' => 'Другое',
            default => (string) $reason,
        };
    }
}
