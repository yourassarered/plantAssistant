<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plant_id' => $this->plant_id,
            'type' => $this->type,
            'interval_days' => $this->interval_days,
            'is_enabled' => (bool) $this->is_enabled,
            'last_done_at' => $this->last_done_at?->toISOString(),
            'next_due_date' => $this->calculateNextDueDate(),
        ];
    }

    // Вспомогательный метод для удобства на фронте
    private function calculateNextDueDate()
    {
        if (!$this->is_enabled) return null;

        if ($this->last_done_at) {
            return $this->last_done_at->copy()->addDays($this->interval_days)->toDateString();
        }

        // Если ухода еще не было, отсчитываем от даты создания растения
        return $this->plant->planted_at->copy()->addDays($this->interval_days)->toDateString();
    }
}