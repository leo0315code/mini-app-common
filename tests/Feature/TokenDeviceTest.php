<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * P3-11 Token 设备信息 + 我的设备/会话管理：
 * 登录写入设备字段、列出设备、单台踢下线、一键踢下线。
 */
class TokenDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function loginWithDevice(string $deviceName, string $userAgent = 'MiniProgram/1.0', string $openid = 'oTEST_OPENID_DEFAULT'): array
    {
        config(['services.mini_program' => ['app_id' => 'test_app_id', 'secret' => 'test_secret']]);
        Http::fake([
            '*' => Http::response(['openid' => $openid, 'session_key' => 'sk']),
        ]);

        $response = $this->withHeader('User-Agent', $userAgent)
            ->postJson('/api/auth/login', ['code' => 'valid_code', 'device_name' => $deviceName]);

        return [
            'user' => User::query()->where('openid', $openid)->first(),
            'token' => $response->json('data.token'),
        ];
    }

    public function test_login_records_device_info(): void
    {
        $this->loginWithDevice('iPhone 15');

        $pat = PersonalAccessToken::query()->latest('id')->first();
        $this->assertEquals('iPhone 15', $pat->device_name);
        $this->assertEquals('MiniProgram/1.0', $pat->user_agent);
    }

    public function test_login_without_device_name_falls_back_to_ua_guess(): void
    {
        config(['services.mini_program' => ['app_id' => 'test_app_id', 'secret' => 'test_secret']]);
        Http::fake(['*' => Http::response(['openid' => 'oGUESS', 'session_key' => 'sk'])]);

        $this->withHeader('User-Agent', 'iPhone; AppleWebKit')
            ->postJson('/api/auth/login', ['code' => 'valid_code'])
            ->assertStatus(200);

        $pat = PersonalAccessToken::query()->where('tokenable_id', User::where('openid', 'oGUESS')->first()->id)->first();
        $this->assertEquals('iOS 设备', $pat->device_name);
    }

    public function test_list_devices_marks_current(): void
    {
        ['user' => $user, 'token' => $token] = $this->loginWithDevice('iPhone 15');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me/devices')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(1, 'data');

        // token 明文不应出现在列表中
        $this->assertArrayNotHasKey('token', $response->json('data.0'));
        $this->assertTrue($response->json('data.0.is_current'));

        // 再登一台设备
        ['token' => $token2] = $this->loginWithDevice('Android', 'And/1.0', 'oTEST_OPENID_DEFAULT');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me/devices')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_revoke_single_device_excludes_it(): void
    {
        ['user' => $user, 'token' => $token] = $this->loginWithDevice('iPhone 15');
        ['token' => $token2] = $this->loginWithDevice('Android', 'And/1.0', 'oTEST_OPENID_DEFAULT');

        $currentTokenId = (int) explode('|', $token)[0];
        $otherToken = PersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->whereKeyNot($currentTokenId)
            ->first();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/me/devices/' . $otherToken->getKey())
            ->assertOk()
            ->assertJsonPath('code', 0);

        // 被踢的设备 token 在 Sanctum 层已不可解析
        $this->assertNull(PersonalAccessToken::findToken(explode('|', $token2)[1]));

        // 被踢的设备 token 访问受保护接口应 401
        // （清除测试单例容器内 sanctum guard 的跨请求 user 缓存，模拟生产每次新解析）
        \Illuminate\Support\Facades\Auth::guard('sanctum')->forgetUser();
        $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/user')
            ->assertStatus(401);

        // 当前设备仍可用
        \Illuminate\Support\Facades\Auth::guard('sanctum')->forgetUser();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user')
            ->assertOk();
    }

    public function test_cannot_revoke_current_device(): void
    {
        ['user' => $user, 'token' => $token] = $this->loginWithDevice('iPhone 15');
        $currentId = (int) explode('|', $token)[0];

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/me/devices/' . $currentId)
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
    }

    public function test_revoke_others_kicks_all_but_current(): void
    {
        ['user' => $user, 'token' => $token] = $this->loginWithDevice('iPhone 15');
        $this->loginWithDevice('Android', 'And/1.0', 'oTEST_OPENID_DEFAULT');
        $this->loginWithDevice('iPad', 'iPad/1.0', 'oTEST_OPENID_DEFAULT');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/me/devices')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.revoked_count', 2);

        // 仅剩当前一台
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me/devices')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
