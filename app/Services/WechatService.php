<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 微信小程序服务：封装 code2session 登录凭证校验 + 手机号解密。
 *
 * 密钥从 config('services.mini_program') 读取，不硬编码。
 */
class WechatService
{
    /** code2session 接口地址 */
    protected const JSCODE2SESSION_URL = 'https://api.weixin.qq.com/sns/jscode2session';

    /** 手机号获取接口地址（新版 code 换手机号） */
    protected const GET_PHONE_NUMBER_URL = 'https://api.weixin.qq.com/wxa/business/getuserphonenumber';

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

    /**
     * 用手机号授权 code 换取手机号（新版接口，无需 session_key 解密）。
     *
     * 小程序端调用 wx.getPhoneNumber() 获取 code，后端用此方法换取手机号。
     *
     * @throws RuntimeException 微信返回错误或网络异常
     */
    public function getPhoneNumber(string $code, string $openid): string
    {
        $config = config('services.mini_program');
        $appId = $config['app_id'] ?? null;
        $secret = $config['secret'] ?? null;

        if (empty($appId) || empty($secret)) {
            throw new RuntimeException('微信小程序 app_id/secret 未配置');
        }

        // 先获取 access_token
        $accessToken = $this->getAccessToken($appId, $secret);

        $response = Http::post(self::GET_PHONE_NUMBER_URL . '?access_token=' . $accessToken, [
            'code' => $code,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('微信手机号接口请求失败：' . $response->body());
        }

        $data = $response->json();

        if (!empty($data['errcode']) && $data['errcode'] !== 0) {
            throw new RuntimeException('获取手机号失败：' . ($data['errmsg'] ?? 'unknown') . ' (' . $data['errcode'] . ')');
        }

        $phoneInfo = $data['phone_info'] ?? [];
        $phoneNumber = $phoneInfo['phoneNumber'] ?? null;

        if (empty($phoneNumber)) {
            throw new RuntimeException('获取手机号失败：未返回手机号');
        }

        return $phoneNumber;
    }

    /**
     * 获取微信接口 access_token（带简单缓存）。
     */
    protected function getAccessToken(string $appId, string $secret): string
    {
        $cacheKey = 'wechat_access_token_' . $appId;

        // 尝试从缓存获取
        $token = cache()->get($cacheKey);
        if ($token) {
            return $token;
        }

        $response = Http::get('https://api.weixin.qq.com/cgi-bin/token', [
            'grant_type' => 'client_credential',
            'appid' => $appId,
            'secret' => $secret,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('获取 access_token 失败：' . $response->body());
        }

        $data = $response->json();

        if (!empty($data['errcode'])) {
            throw new RuntimeException('获取 access_token 失败：' . ($data['errmsg'] ?? 'unknown'));
        }

        $token = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? 7200;

        // 缓存 token，提前 5 分钟过期
        cache()->put($cacheKey, $token, $expiresIn - 300);

        return $token;
    }
}
