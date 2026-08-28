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

    /** 订阅消息发送接口地址 */
    protected const SUBSCRIBE_MESSAGE_SEND_URL = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send';

    /** 最大重试次数 */
    protected const MAX_RETRIES = 2;

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
     * 获取微信接口 access_token（带简单缓存 + 防击穿锁）。
     *
     * 多 worker 并发时，若缓存同时失效会出现「惊群」——多个请求同时打向微信
     * 换 token 接口。用 Cache::lock 保证同一时刻只有一个请求真正请求微信，
     * 其余请求等待锁释放后直接读回写好的缓存。
     */
    protected function getAccessToken(string $appId, string $secret): string
    {
        $cacheKey = 'wechat_access_token_' . $appId;
        $lockKey = 'wechat_access_token_lock_' . $appId;

        // 尝试从缓存获取（命中直接返回，无需加锁）
        $token = cache()->get($cacheKey);
        if ($token) {
            return $token;
        }

        // 未命中：争抢刷新锁，最多阻塞 5s 等待其他 worker 刷新完成
        $lock = cache()->lock($lockKey, 5);
        if (! $lock->block(5)) {
            // 未能获取锁（极端超时）：退化为直接请求，保证可用
            return $this->fetchAccessToken($appId, $secret);
        }

        try {
            // 双重检查：获取锁后缓存可能已被其他 worker 写完
            $token = cache()->get($cacheKey);
            if ($token) {
                return $token;
            }

            return $this->fetchAccessToken($appId, $secret);
        } finally {
            $lock->release();
        }
    }

    /**
     * 真正请求微信换取 access_token 并写缓存（提前 5 分钟过期）。
     */
    protected function fetchAccessToken(string $appId, string $secret): string
    {
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
        cache()->put('wechat_access_token_' . $appId, $token, $expiresIn - 300);

        return $token;
    }

    /**
     * 强制刷新 access_token（当检测到 token 失效时使用）。
     */
    protected function refreshAccessToken(string $appId, string $secret): string
    {
        $cacheKey = 'wechat_access_token_' . $appId;
        cache()->forget($cacheKey);

        return $this->getAccessToken($appId, $secret);
    }

    /**
     * 发送微信订阅消息。
     *
     * @param string $openid 用户 openid
     * @param string $templateId 订阅消息模板 ID
     * @param array $data 模板数据 ['key1' => ['value' => 'xxx'], 'key2' => ['value' => 'yyy']]
     * @param string|null $page 点击跳转的小程序页面
     * @param array $options 额外选项：miniprogram_state(developer/trial/formal), lang(zh_CN)
     * @return array{success: bool, errcode: int, errmsg: string, raw: array}
     */
    public function sendSubscribeMessage(
        string $openid,
        string $templateId,
        array $data,
        ?string $page = null,
        array $options = [],
    ): array {
        $config = config('services.mini_program');
        $appId = $config['app_id'] ?? null;
        $secret = $config['secret'] ?? null;

        if (empty($appId) || empty($secret)) {
            return [
                'success' => false,
                'errcode' => -1,
                'errmsg' => '微信小程序 app_id/secret 未配置',
                'raw' => [],
            ];
        }

        if (empty($templateId)) {
            return [
                'success' => false,
                'errcode' => -2,
                'errmsg' => '订阅消息模板 ID 未配置',
                'raw' => [],
            ];
        }

        $accessToken = $this->getAccessToken($appId, $secret);

        $payload = [
            'touser' => $openid,
            'template_id' => $templateId,
            'data' => $data,
        ];

        if ($page !== null && $page !== '') {
            $payload['page'] = $page;
        }

        if (isset($options['miniprogram_state'])) {
            $payload['miniprogram_state'] = $options['miniprogram_state'];
        }

        if (isset($options['lang'])) {
            $payload['lang'] = $options['lang'];
        }

        $lastResult = null;

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = Http::post(
                    self::SUBSCRIBE_MESSAGE_SEND_URL . '?access_token=' . $accessToken,
                    $payload
                );

                if ($response->failed()) {
                    $lastResult = [
                        'success' => false,
                        'errcode' => -3,
                        'errmsg' => '网络请求失败：HTTP ' . $response->status(),
                        'raw' => ['body' => $response->body()],
                    ];
                    continue;
                }

                $respData = $response->json();
                $errcode = (int) ($respData['errcode'] ?? 0);

                // token 过期或无效，刷新后重试一次
                if (in_array($errcode, [40001, 42001, 40014], true) && $attempt < self::MAX_RETRIES) {
                    $accessToken = $this->refreshAccessToken($appId, $secret);
                    continue;
                }

                if ($errcode === 0) {
                    return [
                        'success' => true,
                        'errcode' => 0,
                        'errmsg' => 'ok',
                        'raw' => $respData,
                    ];
                }

                // 常见订阅消息错误码处理
                $errmsg = $respData['errmsg'] ?? 'unknown';
                if ($errcode === 43101) {
                    $errmsg = '用户未订阅该模板消息（43101）';
                } elseif ($errcode === 40037) {
                    $errmsg = '模板 ID 不正确（40037）';
                } elseif ($errcode === 41028) {
                    $errmsg = 'form_id 不正确或已过期（41028）';
                } elseif ($errcode === 41029) {
                    $errmsg = 'form_id 已被使用（41029）';
                } elseif ($errcode === 41030) {
                    $errmsg = 'page 不正确（41030）';
                } elseif ($errcode === 40003) {
                    $errmsg = 'openid 不正确（40003）';
                }

                $lastResult = [
                    'success' => false,
                    'errcode' => $errcode,
                    'errmsg' => $errmsg,
                    'raw' => $respData,
                ];

                // 非 token 错误不重试
                break;
            } catch (\Throwable $e) {
                $lastResult = [
                    'success' => false,
                    'errcode' => -4,
                    'errmsg' => '发送异常：' . $e->getMessage(),
                    'raw' => ['exception' => get_class($e)],
                ];
            }
        }

        return $lastResult ?? [
            'success' => false,
            'errcode' => -5,
            'errmsg' => '未知错误',
            'raw' => [],
        ];
    }
}
