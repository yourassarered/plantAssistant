<?php

namespace App\Services;

use App\Models\Plant;
use App\Models\Tip;
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
        DB::transaction(function () use ($user, $reason): void {
            $blockReason = $reason ?: 'Аккаунт заблокирован модератором.';
            $now = now();

            $user->blocked_at ??= $now;
            $user->block_reason = $blockReason;
            $user->rank = 0;
            $user->save();

            Plant::where('user_id', $user->id)
                ->where('is_public', true)
                ->update([
                    'is_public' => false,
                    'public_hidden_at' => $now,
                    'public_hidden_reason' => 'Публичный доступ временно скрыт из-за блокировки владельца.',
                    'is_public_locked' => false,
                    'hidden_due_to_block' => true,
                    'was_public_before_block' => true,
                ]);

            Tip::where('author_id', $user->id)
                ->whereNull('deleted_at')
                ->delete();

            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }

    public function unblock(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->blocked_at = null;
            $user->block_reason = null;
            $user->save();

            Plant::where('user_id', $user->id)
                ->where('hidden_due_to_block', true)
                ->where('was_public_before_block', true)
                ->where('is_public_locked', false)
                ->update([
                    'is_public' => true,
                    'public_hidden_at' => null,
                    'public_hidden_reason' => null,
                    'hidden_due_to_block' => false,
                    'was_public_before_block' => false,
                ]);

            Plant::where('user_id', $user->id)
                ->where('hidden_due_to_block', true)
                ->where('was_public_before_block', false)
                ->update([
                    'hidden_due_to_block' => false,
                ]);
        });
    }
}
