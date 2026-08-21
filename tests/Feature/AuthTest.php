<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 登录接口：缺少 code 参数应返回 422。
     */
    public function test_login_requires_code(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
    }

    /**
     * 登录接口：code 过长应返回 422。
     */
    public function test_login_code_max_length(): void
    {
        $this->postJson('/api/auth/login', ['code' => str_repeat('a', 129)])
            ->assertStatus(422);
    }

    /**
     * 登录接口：微信返回错误应返回 401。
     */
    public function test_login_wechat_error_returns_401(): void
    {
        config(['services.mini_program' => ['app_id' => 'test_app_id', 'secret' => 'test_secret']]);

        Http::fake([
            '*' => Http::response([
                'errcode' => 40029,
                'errmsg' => 'invalid code',
            ]),
        ]);

        $this->postJson('/api/auth/login', ['code' => 'invalid_code'])
            ->assertStatus(401)
            ->assertJsonPath('code', 40100);
    }

    /**
     * 登录接口：成功登录应返回 token 和用户信息。
     */
    public function test_login_success_returns_token(): void
    {
        config(['services.mini_program' => ['app_id' => 'test_app_id', 'secret' => 'test_secret']]);

        Http::fake([
            '*' => Http::response([
                'openid' => 'oTEST_OPENID_123',
                'session_key' => 'sk_test_key',
            ]),
        ]);

        $response = $this->postJson('/api/auth/login', ['code' => 'valid_code'])
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '登录成功');

        $response->assertJsonStructure([
            'data' => [
                'token',
                'token_type',
                'user' => [
                    'id',
                    'openid',
                    'nickname',
                    'avatar',
                    'gender',
                    'phone',
                    'meta',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

        // 验证用户已创建
        $this->assertDatabaseHas('users', [
            'openid' => 'oTEST_OPENID_123',
            'nickname' => '微信用户',
        ]);
    }

    /**
     * 登录接口：同一 openid 再次登录应返回同一用户（幂等）。
     */
    public function test_login_is_idempotent_for_same_openid(): void
    {
        config(['services.mini_program' => ['app_id' => 'test_app_id', 'secret' => 'test_secret']]);

        Http::fake([
            '*' => Http::response([
                'openid' => 'oTEST_OPENID_123',
                'session_key' => 'sk_test_key',
            ]),
        ]);

        // 第一次登录
        $response1 = $this->postJson('/api/auth/login', ['code' => 'code_1']);
        $userId1 = $response1->json('data.user.id');

        // 第二次登录
        $response2 = $this->postJson('/api/auth/login', ['code' => 'code_2']);
        $userId2 = $response2->json('data.user.id');

        // 应为同一用户
        $this->assertEquals($userId1, $userId2);
        $this->assertEquals(1, User::where('openid', 'oTEST_OPENID_123')->count());
    }

    /**
     * 登录接口：unionid 应被正确保存。
     */
    public function test_login_saves_unionid(): void
    {
        config(['services.mini_program' => ['app_id' => 'test_app_id', 'secret' => 'test_secret']]);

        Http::fake([
            '*' => Http::response([
                'openid' => 'oTEST_OPENID_123',
                'unionid' => 'uTEST_UNIONID_456',
                'session_key' => 'sk_test_key',
            ]),
        ]);

        $this->postJson('/api/auth/login', ['code' => 'valid_code'])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'openid' => 'oTEST_OPENID_123',
            'unionid' => 'uTEST_UNIONID_456',
        ]);
    }

    /**
     * 退出登录：未登录应返回 401。
     */
    public function test_logout_requires_auth(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertStatus(401);
    }

    /**
     * 退出登录：成功退出应吊销 token。
     */
    public function test_logout_invalidates_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mini-program')->plainTextToken;

        // 验证 token 已创建
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ])
            ->assertStatus(200)
            ->assertJsonPath('code', 0);

        // 验证 token 已被删除
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // 使用新 token 验证用户接口正常工作
        $newToken = $user->createToken('mini-program')->plainTextToken;
        $this->getJson('/api/user', [
            'Authorization' => 'Bearer ' . $newToken,
        ])
            ->assertStatus(200);
    }
}
