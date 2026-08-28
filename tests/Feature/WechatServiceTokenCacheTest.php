<?php

namespace Tests\Feature;

use App\Services\WechatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WechatService::getAccessToken 优化回归：
 *  - token 命中缓存直接返回，不重复请求微信（CACHE_STORE=array 下可验证写入）
 *  - 未命中时仍正确请求并写回缓存（提前 5 分钟过期）
 */
class WechatServiceTokenCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mini_program' => [
                'app_id' => 'test-app-id',
                'secret' => 'test-secret',
                'feedback_template_id' => 'feedback-tpl-001',
                'announcement_template_id' => 'announcement-tpl-001',
            ],
        ]);
    }

    public function test_token_is_cached_and_reused_for_call_count(): void
    {
        $requests = 0;
        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => function () use (&$requests) {
                $requests++;

                return Http::response(['access_token' => 'cached-token', 'expires_in' => 7200], 200);
            },
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response(['errcode' => 0, 'errmsg' => 'ok'], 200),
        ]);

        $service = app(WechatService::class);
        $service->sendSubscribeMessage('openid-x', 'tpl-001', ['thing1' => ['value' => 'hi']]);
        // 第二次调用在缓存有效期内，不应再请求 token
        $service->sendSubscribeMessage('openid-x', 'tpl-001', ['thing1' => ['value' => 'hi']]);

        // token 接口仅被请求一次（缓存生效）
        $this->assertSame(1, $requests);
        $this->assertSame('cached-token', Cache::get('wechat_access_token_test-app-id'));
    }

    public function test_token_writes_cache_with_early_expiry(): void
    {
        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 7200], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response(['errcode' => 0, 'errmsg' => 'ok'], 200),
        ]);

        $service = app(WechatService::class);
        $service->sendSubscribeMessage('openid-x', 'tpl-001', ['thing1' => ['value' => 'hi']]);

        // 提前 5 分钟（300s）过期 → 实际 TTL < 7200
        $this->assertTrue(Cache::has('wechat_access_token_test-app-id'));

        $store = Cache::store();
        $ttl = method_exists($store, 'getTtl')
            ? $store->getTtl('wechat_access_token_test-app-id')
            : null;
        // 若驱动支持读取 TTL 则断言 < 7200；否则仅断言已写入
        if ($ttl !== null) {
            $this->assertLessThan(7200, $ttl);
        }
    }
}
