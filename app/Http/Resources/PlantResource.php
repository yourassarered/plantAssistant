<?php

namespace App\Http\Resources;

use App\Models\CareLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PlantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canManage = (bool) $request->user()?->can('update', $this->resource);
        $canDelete = (bool) $request->user()?->can('delete', $this->resource);
        $canCompleteCare = (bool) $request->user()?->can('create', [
            CareLog::class,
            $this->user_id,
        ]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'planted_at' => $this->planted_at?->toISOString(),
            'height' => $this->height,
            'is_public' => $this->is_public,
            'is_public_locked' => (bool) $this->is_public_locked,
            'public_hidden_at' => $this->public_hidden_at?->toISOString(),
            'public_hidden_reason' => $this->when($canManage, $this->public_hidden_reason),
            'hidden_due_to_block' => (bool) $this->hidden_due_to_block,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'owner' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'rank' => $this->user->rank,
                    'is_blocked' => $this->user->isBlocked(),
                    'avatar_url' => $this->user->avatar_path
                        ? Storage::disk('public')->url($this->user->avatar_path)
                        : asset('images/placeholders/avatar-placeholder.png'),
                ]
            ),
            'room' => new RoomResource($this->whenLoaded('room')),
            'room_id' => $this->room_id,
            'latest_image' => new PlantImageResource($this->whenLoaded('latestImage')),
            'care_settings' => CareSettingResource::collection($this->whenLoaded('careSettings')),
            'care_logs' => CareLogResource::collection($this->whenLoaded('careLogs')),
            'tips' => TipResource::collection($this->whenLoaded('tips')),
            'likes_count' => $this->when(isset($this->likes_count), $this->likes_count ?? $this->likes->count()),
            'report_summary' => [
                'total' => (int) ($this->pending_reports_count ?? 0)
                    + (int) ($this->accepted_reports_count ?? 0)
                    + (int) ($this->rejected_reports_count ?? 0),
                'pending' => (int) ($this->pending_reports_count ?? 0),
                'accepted' => (int) ($this->accepted_reports_count ?? 0),
                'rejected' => (int) ($this->rejected_reports_count ?? 0),
            ],
            'user_liked' => $this->when(
                $request->user() && $this->relationLoaded('likes'),
                $this->likes->contains('user_id', $request->user()?->id)
            ),
            'can_manage' => $canManage,
            'can_delete' => $canDelete,
            'can_complete_care' => $canCompleteCare,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
