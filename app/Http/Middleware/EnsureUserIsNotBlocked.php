<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBlocked
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBlocked()) {
            $request->user()->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Аккаунт заблокирован. Вход и действия в системе недоступны.',
                'status' => 403,
            ], 403);
        }

        return $next($request);
    }
}
