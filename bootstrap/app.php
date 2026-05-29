<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureUserIsNotBlocked;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\TrackApiTraffic;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            TrackApiTraffic::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'not_blocked' => EnsureUserIsNotBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Обработка 404 для API маршрутов
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Ресурс не найден',
                    'status' => 404,
                ], 404);
            }
        });

        // Обработка 405 Method Not Allowed для API
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Метод не разрешён для этого маршрута',
                    'status' => 405,
                ], 405);
            }
        });

        // Обработка всех остальных ошибок для API
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                // Если это валидация
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'message' => 'Ошибка валидации',
                        'errors' => $e->errors(),
                        'status' => 422,
                    ], 422);
                }

                // Если это аутентификация
                if ($e instanceof AuthenticationException) {
                    return response()->json([
                        'message' => 'Требуется аутентификация',
                        'status' => 401,
                    ], 401);
                }

                // Если это авторизация
                if ($e instanceof AuthorizationException) {
                    return response()->json([
                        'message' => 'Доступ запрещён',
                        'status' => 403,
                    ], 403);
                }

                // Для всех остальных ошибок
                return response()->json([
                    'message' => $e->getMessage() ?: 'Ошибка сервера',
                    'status' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
                ], method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500);
            }
        });
    })->create();
