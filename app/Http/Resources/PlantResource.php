<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'planted_at' => $this->planted_at?->toISOString(),
            'height' => $this->height,
            'is_public' => $this->is_public,
            'user_id' => $this->user_id,
            'room' => new RoomResource($this->whenLoaded('room')),
            'room_id' => $this->room_id,
            'care_settings' => CareSettingResource::collection($this->whenLoaded('careSettings')),
            'care_logs' => CareLogResource::collection($this->whenLoaded('careLogs')),
            'tips' => TipResource::collection($this->whenLoaded('tips')),
            'likes_count' => $this->when(isset($this->likes_count), $this->likes_count ?? $this->likes->count()),
            'user_liked' => $this->when(
                $request->user() && $this->relationLoaded('likes'),
                $this->likes->contains('user_id', $request->user()?->id)
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}