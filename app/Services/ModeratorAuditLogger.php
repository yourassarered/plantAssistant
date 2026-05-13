<?php

namespace App\Services;

use App\Models\ModeratorAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class ModeratorAuditLogger
{
    public function log(
        User $actor,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $payload = null,
        ?Request $request = null
    ): void {
        ModeratorAuditLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'payload' => $payload,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
