<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserSanctionService
{
    public const WARNING_LIMIT = 3;

    public function warn(User $user, ?string $reason = null): array
    {
        $user->warnings_count = min(self::WARNING_LIMIT, ((int) $user->warnings_count) + 1);
        $blocked = false;

        if ($user->warnings_count >= self::WARNING_LIMIT && ! $user->isBlocked()) {
            $this->block($user, $reason ?: 'Автоматическая блокировка после 3 предупреждений.');
            $blocked = true;
        } else {
            $user->save();
        }

        return [
            'warnings_count' => $user->warnings_count,
            'blocked' => $blocked || $user->isBlocked(),
            'is_final_warning' => $user->warnings_count >= self::WARNING_LIMIT,
        ];
    }

    public function block(User $user, ?string $reason = null): void
    {
        $user->blocked_at ??= now();
        $user->block_reason = $reason ?: 'Аккаунт заблокирован модератором.';
        $user->save();

        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
