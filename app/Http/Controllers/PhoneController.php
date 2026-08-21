<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\WechatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PhoneController extends Controller
{
    public function __construct(
        protected WechatService $wechat
    ) {}

    /**
     * 手机号授权：用小程序 wx.getPhoneNumber() 返回的 code 换取手机号并绑定到当前用户。
     *
     * POST /api/user/phone
     * Body: { "code": "手机号授权code" }
     */
    public function bind(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:128'],
        ]);

        $user = $request->user();

        try {
            $phoneNumber = $this->wechat->getPhoneNumber($request->input('code'), $user->openid);
        } catch (RuntimeException $e) {
            return response()->json([
                'code' => 40101,
                'message' => $e->getMessage(),
            ], 401);
        }

        $user->phone = $phoneNumber;
        $user->save();

        return response()->json([
            'code' => 0,
            'message' => '手机号绑定成功',
            'data' => new UserResource($user->fresh()),
        ]);
    }
}
