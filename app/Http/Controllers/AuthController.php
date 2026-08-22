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

        $token = $user->createToken('mini-program')->plainTextToken;

        return response()->json([
            'code' => 0,
            'message' => '登录成功',
            'data' => [
                'token' => $token,
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
}
