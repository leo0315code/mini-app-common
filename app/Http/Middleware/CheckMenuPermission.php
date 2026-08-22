<?php

namespace App\Http\Middleware;

use App\Support\MenuPermissionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuPermission
{
    public function __construct(
        protected MenuPermissionManager $permissionManager,
    ) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'code' => 40100,
                'message' => '未授权，请先登录',
            ], 401);
        }

        if (empty($permissions)) {
            return $next($request);
        }

        $hasAny = $this->permissionManager->hasAnyPermission($user, $permissions);

        if (! $hasAny) {
            return response()->json([
                'code' => 40300,
                'message' => '无权限访问该接口',
                'required_permissions' => $permissions,
            ], 403);
        }

        return $next($request);
    }
}
