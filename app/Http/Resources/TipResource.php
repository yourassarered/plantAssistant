<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plant_id' => $this->plant_id,
            'author' => new UserResource($this->whenLoaded('author')),
            'content' => $this->content,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}