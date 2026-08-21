<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhoneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 手机号绑定：未登录应返回 401。
     */
    public function test_bind_phone_requires_auth(): void
    {
        $this->postJson('/api/user/phone', ['code' => 'test_code'])
            ->assertStatus(401);
    }

    /**
     * 手机号绑定：缺少 code 应返回 422。
     */
    public function test_bind_phone_requires_code(): void
    {
        $user = User::factory()->create(['openid' => 'oTEST_OPENID']);

        $this->actingAs($user)
            ->postJson('/api/user/phone', [])
            ->assertStatus(422);
    }

    /**
     * 手机号绑定：成功绑定手机号。
     */
    public function test_bind_phone_success(): void
    {
        config(['services.mini_program' => ['app_id' => 'test_app_id', 'secret' => 'test_secret']]);

        $user = User::factory()->create(['openid' => 'oTEST_OPENID']);

        // 模拟 access_token 接口
        Http::fake([
            '*' => Http::sequence()
                ->push(['access_token' => 'test_access_token', 'expires_in' => 7200])
                ->push([
                    'errcode' => 0,
                    'errmsg' => 'ok',
                    'phone_info' => [
                        'phoneNumber' => '13800138000',
                        'purePhoneNumber' => '13800138000',
                        'countryCode' => '86',
                    ],
                ]),
        ]);

        $this->actingAs($user)
            ->postJson('/api/user/phone', ['code' => 'phone_code'])
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '手机号绑定成功')
            ->assertJsonPath('data.phone', '13800138000');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '13800138000',
        ]);
    }

    /**
     * 手机号绑定：微信接口错误应返回 401。
     */
    public function test_bind_phone_wechat_error(): void
    {
        config(['services.mini_program' => ['app_id' => 'test_app_id', 'secret' => 'test_secret']]);

        $user = User::factory()->create(['openid' => 'oTEST_OPENID']);

        Http::fake([
            '*' => Http::sequence()
                ->push(['access_token' => 'test_access_token', 'expires_in' => 7200])
                ->push([
                    'errcode' => 40029,
                    'errmsg' => 'invalid code',
                ]),
        ]);

        $this->actingAs($user)
            ->postJson('/api/user/phone', ['code' => 'invalid_code'])
            ->assertStatus(401)
            ->assertJsonPath('code', 40101);
    }
}
