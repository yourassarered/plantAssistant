<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plant_id' => $this->plant_id,
            'type' => $this->type,
            'performed_at' => $this->performed_at?->toISOString(),
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}