<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * 获取当前登录用户信息（需 auth:sanctum）。
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
