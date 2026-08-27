<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * C 端 API 全局限流（throttle:api，60 次/分钟/用户，未登录按 IP）。
 *
 * 背景：withRouting(api:) 默认不挂 throttle，须在 bootstrap/app.php 显式
 * $middleware->throttleApi() 才会把 throttle:api 加入 api 组。本测试作为
 * 行为级防护，防止未来删除该调用导致全 API 无频控。
 */
class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_is_rate_limited_by_ip(): void
    {
        // 清空可能存在的计数，保证测试确定性
        RateLimiter::clear('api:127.0.0.1');

        $limit = 60; // 与 AppServiceProvider 定义的 api limiter 一致

        // 前 limit 次正常通过
        for ($i = 0; $i < $limit; $i++) {
            $resp = $this->getJson('/api/banners');
            $resp->assertStatus(200);
        }

        // 第 limit+1 次触发 429，且 JSON 格式符合全局异常约定
        $this->getJson('/api/banners')
            ->assertStatus(429)
            ->assertJsonPath('code', 42900)
            ->assertJsonPath('message', '请求过于频繁，请稍后再试');
    }

    public function test_login_is_rate_limited_by_ip(): void
    {
        RateLimiter::clear('api:127.0.0.1');

        $limit = 60;

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/auth/login', [
                'code' => 'invalid-code-'.$i,
            ])->assertStatus(401); // 未到限流：正常业务响应（登录失败）
        }

        // 超限后即使请求合法也会被节流
        $this->postJson('/api/auth/login', ['code' => 'x'])
            ->assertStatus(429);
    }
}
