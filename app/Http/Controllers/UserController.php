<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * 获取当前登录用户信息（需 auth:sanctum）。
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => new UserResource($request->user()),
        ]);
    }

    /**
     * 更新当前用户资料（需 auth:sanctum）。
     *
     * 支持更新：nickname、avatar、gender、meta
     */
    public function update(UpdateUserRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->fill($request->only(['nickname', 'avatar', 'gender', 'meta']));
        $user->save();

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => new UserResource($user->fresh()),
        ]);
    }
}
