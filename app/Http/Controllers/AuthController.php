<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WechatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        protected WechatService $wechat
    ) {}

    /**
     * 微信小程序登录：code → openid → 签发 Sanctum Token。
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        try {
            $result = $this->wechat->code2Session($request->input('code'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
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

        $token = $user->createToken('mini-program')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * 退出登录：仅吊销当前 Token。
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => '已退出登录']);
    }
}
