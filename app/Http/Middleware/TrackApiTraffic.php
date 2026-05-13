<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackApiTraffic
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/*')) {
            $minute = now()->format('YmdHi');
            $route = $request->route()?->uri() ?? 'unknown';
            $method = $request->method();
            $status = (int) $response->getStatusCode();

            $this->incrementWithTtl("metrics:traffic:total:{$minute}");
            $this->incrementWithTtl("metrics:traffic:route:{$minute}:{$method}:{$route}");
            $this->incrementWithTtl("metrics:traffic:status:{$minute}:{$status}");
        }

        return $response;
    }

    private function incrementWithTtl(string $key): void
    {
        $result = Cache::increment($key);
        if ($result === false) {
            Cache::put($key, 1, 172800);

            return;
        }

        Cache::put($key, $result, 172800);
    }
}
