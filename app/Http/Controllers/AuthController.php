<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\WechatService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        protected WechatService $wechat
    ) {}

    /**
     * 微信小程序登录：code → openid → 签发 Sanctum Token。
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->wechat->code2Session($request->input('code'));
        } catch (RuntimeException $e) {
            return response()->json([
                'code' => 40100,
                'message' => $e->getMessage(),
            ], 401);
        }

        // 按 openid 幂等创建/获取用户
        $user = User::query()->firstOrCreate(
            ['openid' => $result['openid']],
            [
                'unionid' => $result['unionid'],
                'nickname' => '微信用户',
            ]
        );

        // 若 unionid 后续补齐，则回写
        if (empty($user->unionid) && !empty($result['unionid'])) {
            $user->unionid = $result['unionid'];
            $user->save();
        }

        // 封禁用户禁止登录并吊销已有登录态
        if ($user->isBanned()) {
            $user->tokens()->delete();

            return response()->json([
                'code' => 40301,
                'message' => '账号已被封禁'.(filled($user->ban_reason) ? '：'.$user->ban_reason : ''),
            ], 403);
        }

        $tokenResult = $user->createToken('mini-program');
        // P3-11：记录登录设备信息，用于「我的设备 / 会话管理 / 一键踢下线」
        $tokenResult->accessToken->forceFill([
            'device_name' => trim((string) $request->input('device_name', '')) ?: ($this->guessDeviceName($request) ?? '未知设备'),
            'user_agent' => (string) $request->header('User-Agent', $request->input('user_agent', '')),
        ])->save();

        return response()->json([
            'code' => 0,
            'message' => '登录成功',
            'data' => [
                'token' => $tokenResult->plainTextToken,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * 退出登录：仅吊销当前 Token。
     */
    public function logout(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'code' => 0,
            'message' => '已退出登录',
        ]);
    }

    /**
     * 根据 User-Agent 粗略推断设备名（仅作缺省值，前端可显式传 device_name 覆盖）。
     */
    private function guessDeviceName(\Illuminate\Http\Request $request): ?string
    {
        $ua = (string) $request->header('User-Agent', '');
        if ($ua === '') {
            return null;
        }
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            return 'iOS 设备';
        }
        if (preg_match('/Android/i', $ua)) {
            return 'Android 设备';
        }
        if (preg_match('/Windows/i', $ua)) {
            return 'Windows 设备';
        }
        if (preg_match('/Macintosh|Mac OS/i', $ua)) {
            return 'Mac 设备';
        }
        return '未知设备';
    }
}
