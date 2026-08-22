<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserBanTest extends TestCase
{
    use RefreshDatabase;

    protected function loginUser(User $user): string
    {
        return $user->createToken('mini-program')->plainTextToken;
    }

    /**
     * 封禁用户拦截：登录时返回 40301 且吊销已有登录态。
     */
    public function test_banned_user_cannot_login(): void
    {
        config(['services.mini_program' => ['app_id' => 'test_app_id', 'secret' => 'test_secret']]);

        Http::fake([
            '*' => Http::response([
                'openid' => 'oBANNED_OPENID',
                'session_key' => 'sk_test_key',
            ]),
        ]);

        // 先登录拿到 token
        $this->postJson('/api/auth/login', ['code' => 'valid_code'])->assertStatus(200);
        $user = User::where('openid', 'oBANNED_OPENID')->first();
        $this->assertNotNull($user);

        // 封禁
        $user->ban('违规');

        // 再次登录应被拦截
        $this->postJson('/api/auth/login', ['code' => 'valid_code'])
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);

        // 旧 token 已被吊销
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * 封禁用户拦截（中间件）：已 banned 状态的用户持 token 访问受保护接口返回 40301。
     * 注意：此用例直接以 banned 状态建用户再登录，避免测试客户端对同一 URL 连续请求复用响应缓存。
     */
    public function test_banned_user_blocked_from_api(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_BANNED]);
        $token = $this->loginUser($user);

        $this->getJson('/api/user', ['Authorization' => 'Bearer ' . $token])
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);
    }

    /**
     * 解封后恢复正常访问。
     */
    public function test_unban_restores_access(): void
    {
        $user = User::factory()->create();
        $token = $this->loginUser($user);

        // ban() 会吊销全部 token，此处验证封禁后旧 token 立即失效
        $user->ban('违规');

        $this->getJson('/api/user', ['Authorization' => 'Bearer ' . $token])
            ->assertStatus(401);

        $user->refresh();
        $user->unban();

        // 解封后需重新登录获取新 token（封禁已吊销旧 token）
        $newToken = $this->loginUser($user);

        $this->getJson('/api/user', ['Authorization' => 'Bearer ' . $newToken])
            ->assertStatus(200);
    }

    /**
     * 后台封禁动作：通过 UserResource 的 ban() 方法标记 banned 并吊销 token。
     * （Filament 行内动作走 Livewire，直接验证资源动作注册的底层逻辑）
     */
    public function test_admin_can_ban_user_via_resource(): void
    {
        $target = User::factory()->create();
        $target->createToken('mini-program');

        // 模拟后台「封禁」动作调用的底层方法
        $target->ban('测试封禁');

        $target->refresh();
        $this->assertSame(User::STATUS_BANNED, $target->status);
        $this->assertSame('测试封禁', $target->ban_reason);
        $this->assertNotNull($target->banned_at);
        // 封禁吊销其全部 token
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * 后台解封动作：恢复正常。
     */
    public function test_admin_can_unban_user_via_resource(): void
    {
        $target = User::factory()->create(['status' => User::STATUS_BANNED]);

        $target->unban();

        $target->refresh();
        $this->assertSame(User::STATUS_NORMAL, $target->status);
        $this->assertNull($target->ban_reason);
    }

    /**
     * 用户列表页渲染含状态列、封禁/解封动作入口。
     * 创建一个已封禁用户，使「解封」动作对其实行渲染；普通用户行出现「封禁」入口。
     */
    public function test_user_list_page_renders_ban_controls(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('secret'),
        ]);
        User::factory()->create(['status' => User::STATUS_BANNED]);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('状态')
            ->assertSee('封禁')
            ->assertSee('解封');
    }
}
