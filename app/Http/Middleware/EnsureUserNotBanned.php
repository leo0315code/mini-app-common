<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 拦截已封禁用户：封禁后即使持有旧 Token，也禁止访问小程序接口。
 */
class EnsureUserNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBanned()) {
            return response()->json([
                'code' => 40301,
                'message' => '账号已被封禁'.(filled($user->ban_reason) ? '：'.$user->ban_reason : ''),
            ], 403);
        }

        return $next($request);
    }
}
