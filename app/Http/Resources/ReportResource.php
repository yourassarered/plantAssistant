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
            'reason' => $this->reason,
            'details' => $this->details,
            'target' => $this->resolveTarget(),
            'moderation_effect' => $this->resolveModerationEffect(),
            'admin_comment' => $this->admin_comment,
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
                    'is_public' => (bool) $plant->is_public,
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
                ],
                'plant' => [
                    'id' => $tip->plant?->id,
                    'name' => $tip->plant?->name,
                    'owner_id' => $tip->plant?->user?->id,
                    'owner_name' => $tip->plant?->user?->name,
                    'is_public' => (bool) $tip->plant?->is_public,
                ],
            ];
        }

        return null;
    }

    private function resolveModerationEffect(): array
    {
        if ($this->target_type === Report::TARGET_TIP) {
            return match ($this->status) {
                'accepted' => [
                    'code' => 'tip_rank_penalty_applied',
                    'summary' => 'Жалоба принята: ранг автора совета уже снижен на 1.',
                ],
                'rejected' => [
                    'code' => 'tip_no_penalty',
                    'summary' => 'Жалоба отклонена: штрафы для автора совета не применялись.',
                ],
                default => [
                    'code' => 'tip_rank_penalty_pending',
                    'summary' => 'Если жалоба будет принята, ранг автора совета снизится на 1.',
                ],
            };
        }

        return match ($this->status) {
            'accepted' => [
                'code' => 'plant_manual_review_applied',
                'summary' => 'Жалоба принята и зафиксирована. Автоматических санкций для растения нет.',
            ],
            'rejected' => [
                'code' => 'plant_no_penalty',
                'summary' => 'Жалоба отклонена. Автоматические санкции для растения не применялись.',
            ],
            default => [
                'code' => 'plant_manual_review_pending',
                'summary' => 'Если жалоба будет принята, она только зафиксируется. Автоматических санкций для растения нет.',
            ],
        };
    }
}
