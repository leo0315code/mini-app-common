<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 微信小程序服务：封装 code2session 登录凭证校验。
 *
 * 密钥从 config('services.mini_program') 读取，不硬编码。
 */
class WechatService
{
    /** code2session 接口地址 */
    protected const JSCODE2SESSION_URL = 'https://api.weixin.qq.com/sns/jscode2session';

    /**
     * 用小程序登录 code 换取 openid / unionid / session_key。
     *
     * @throws RuntimeException 微信返回错误或网络异常
     */
    public function code2Session(string $code): array
    {
        $config = config('services.mini_program');
        $appId = $config['app_id'] ?? null;
        $secret = $config['secret'] ?? null;

        if (empty($appId) || empty($secret)) {
            throw new RuntimeException('微信小程序 app_id/secret 未配置');
        }

        $response = Http::get(self::JSCODE2SESSION_URL, [
            'appid' => $appId,
            'secret' => $secret,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('微信接口请求失败：' . $response->body());
        }

        $data = $response->json();

        // 微信在出错时返回 errcode（0 表示成功，字段可缺省）
        if (!empty($data['errcode'])) {
            throw new RuntimeException('微信登录失败：' . ($data['errmsg'] ?? 'unknown') . ' (' . $data['errcode'] . ')');
        }

        if (empty($data['openid'])) {
            throw new RuntimeException('微信登录失败：未返回 openid');
        }

        return [
            'openid' => $data['openid'],
            'unionid' => $data['unionid'] ?? null,
            'session_key' => $data['session_key'] ?? null,
        ];
    }
}
