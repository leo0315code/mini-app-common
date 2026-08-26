<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * P3-11 我的设备 / 会话管理：列出当前用户所有登录设备，支持单台踢下线与一键踢下线。
 */
class DeviceController extends Controller
{
    /**
     * 列出当前用户的所有登录设备（会话）。
     * 脱敏：不含 token 明文，标记 is_current 标识当前请求所用设备。
     */
    public function index(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()->getKey();

        $devices = $request->user()->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(function (PersonalAccessToken $token) use ($currentTokenId) {
                return [
                    'id' => $token->getKey(),
                    'device_name' => $token->device_name,
                    'user_agent' => $token->user_agent,
                    'last_used_at' => $token->last_used_at?->toDateTimeString(),
                    'created_at' => $token->created_at?->toDateTimeString(),
                    'is_current' => $token->getKey() === $currentTokenId,
                ];
            });

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $devices,
        ]);
    }

    /**
     * 踢下线指定设备（仅限当前用户自己的 token，且不能踢当前设备）。
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->tokens()->find($id);

        if (! $token) {
            return response()->json([
                'code' => 40400,
                'message' => '设备不存在或不属于当前用户',
            ], 404);
        }

        if ($token->getKey() === $request->user()->currentAccessToken()->getKey()) {
            return response()->json([
                'code' => 42200,
                'message' => '不能踢出当前登录的设备，请使用退出登录',
            ], 422);
        }

        $token->delete();

        return response()->json([
            'code' => 0,
            'message' => '该设备已踢下线',
        ]);
    }

    /**
     * 一键踢下线：吊销除当前设备外的所有其他登录会话。
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()->getKey();

        $revoked = $request->user()->tokens()
            ->whereKeyNot($currentTokenId)
            ->delete();

        return response()->json([
            'code' => 0,
            'message' => '已踢下线其他所有设备',
            'data' => ['revoked_count' => $revoked],
        ]);
    }
}
