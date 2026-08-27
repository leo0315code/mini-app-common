<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.banned' => \App\Http\Middleware\EnsureUserNotBanned::class,
            'menu.permission' => \App\Http\Middleware\CheckMenuPermission::class,
        ]);

        // 启用 api 组限流（throttle:api，RateLimiter::for('api') 在 AppServiceProvider 定义：
        // 60 次/分钟/用户(未登录按 IP)。withRouting(api:) 不会自动挂 throttle，需显式启用）
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API 请求统一返回 JSON 格式
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 统一 API 错误响应格式
        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null; // 非 API 请求使用默认处理
            }

            // 参数校验失败
            if ($e instanceof ValidationException) {
                return response()->json([
                    'code' => 42200,
                    'message' => '参数校验失败',
                    'errors' => $e->errors(),
                ], 422);
            }

            // 401 未授权（Laravel 认证异常）
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'code' => 40100,
                    'message' => '未授权，请先登录',
                ], 401);
            }

            // 404 未找到
            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'code' => 40400,
                    'message' => '接口不存在',
                ], 404);
            }

            // 405 方法不允许
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return response()->json([
                    'code' => 40500,
                    'message' => '请求方法不允许',
                ], 405);
            }

            // 401 未授权（Symfony HTTP 异常）
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException) {
                return response()->json([
                    'code' => 40100,
                    'message' => '未授权，请先登录',
                ], 401);
            }

            // 403 禁止访问
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
                return response()->json([
                    'code' => 40300,
                    'message' => '禁止访问',
                ], 403);
            }

            // 429 限流
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException) {
                return response()->json([
                    'code' => 42900,
                    'message' => '请求过于频繁，请稍后再试',
                ], 429);
            }

            // 其他 HTTP 异常
            if ($e instanceof HttpException) {
                return response()->json([
                    'code' => $e->getStatusCode() * 100,
                    'message' => $e->getMessage() ?: '请求失败',
                ], $e->getStatusCode());
            }

            // 服务器内部错误（生产环境隐藏详情）
            $statusCode = 500;
            $message = config('app.debug') ? $e->getMessage() : '服务器内部错误';

            return response()->json([
                'code' => 50000,
                'message' => $message,
            ], $statusCode);
        });
    })->create();
