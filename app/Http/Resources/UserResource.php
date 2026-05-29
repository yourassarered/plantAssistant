<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeEmail = $request->user()?->id === $this->id
            || $request->user()?->isAdmin()
            || $request->is('api/auth/login')
            || $request->is('api/auth/register');
        $canSeeModeration = (bool) $request->user()?->isAdmin();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($canSeeEmail, $this->email),
            'rank' => $this->rank,
            'warnings_count' => $this->when($canSeeModeration, (int) $this->warnings_count),
            'blocked_at' => $this->when($canSeeModeration, $this->blocked_at?->toISOString()),
            'block_reason' => $this->when($canSeeModeration, $this->block_reason),
            'is_blocked' => $this->when($canSeeModeration, $this->isBlocked()),
            'avatar_url' => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : asset('images/placeholders/avatar-placeholder.png'),
            'role' => [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
