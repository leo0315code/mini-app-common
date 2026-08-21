<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 获取用户信息：未登录应返回 401。
     */
    public function test_get_user_requires_auth(): void
    {
        $this->getJson('/api/user')
            ->assertStatus(401);
    }

    /**
     * 获取用户信息：成功返回当前用户。
     */
    public function test_get_user_returns_current_user(): void
    {
        $user = User::factory()->create([
            'openid' => 'oTEST_OPENID',
            'nickname' => '测试用户',
            'avatar' => 'https://example.com/avatar.png',
            'gender' => 1,
        ]);

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.openid', 'oTEST_OPENID')
            ->assertJsonPath('data.nickname', '测试用户');
    }

    /**
     * 更新用户信息：未登录应返回 401。
     */
    public function test_update_user_requires_auth(): void
    {
        $this->putJson('/api/user', ['nickname' => '新昵称'])
            ->assertStatus(401);
    }

    /**
     * 更新用户信息：成功更新昵称。
     */
    public function test_update_user_nickname(): void
    {
        $user = User::factory()->create(['openid' => 'oTEST_OPENID']);

        $this->actingAs($user)
            ->putJson('/api/user', ['nickname' => '新昵称'])
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '更新成功')
            ->assertJsonPath('data.nickname', '新昵称');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nickname' => '新昵称',
        ]);
    }

    /**
     * 更新用户信息：成功更新头像。
     */
    public function test_update_user_avatar(): void
    {
        $user = User::factory()->create(['openid' => 'oTEST_OPENID']);

        $this->actingAs($user)
            ->putJson('/api/user', ['avatar' => 'https://example.com/new_avatar.png'])
            ->assertStatus(200)
            ->assertJsonPath('data.avatar', 'https://example.com/new_avatar.png');
    }

    /**
     * 更新用户信息：成功更新性别。
     */
    public function test_update_user_gender(): void
    {
        $user = User::factory()->create(['openid' => 'oTEST_OPENID']);

        $this->actingAs($user)
            ->putJson('/api/user', ['gender' => 2])
            ->assertStatus(200)
            ->assertJsonPath('data.gender', 2);
    }

    /**
     * 更新用户信息：性别值无效应返回 422。
     */
    public function test_update_user_invalid_gender(): void
    {
        $user = User::factory()->create(['openid' => 'oTEST_OPENID']);

        $this->actingAs($user)
            ->putJson('/api/user', ['gender' => 3])
            ->assertStatus(422);
    }

    /**
     * 更新用户信息：成功更新 meta 扩展字段。
     */
    public function test_update_user_meta(): void
    {
        $user = User::factory()->create(['openid' => 'oTEST_OPENID']);

        $meta = ['level' => 10, 'vip' => true];

        $this->actingAs($user)
            ->putJson('/api/user', ['meta' => $meta])
            ->assertStatus(200)
            ->assertJsonPath('data.meta.level', 10)
            ->assertJsonPath('data.meta.vip', true);
    }

    /**
     * 更新用户信息：avatar URL 格式错误应返回 422。
     */
    public function test_update_user_invalid_avatar_url(): void
    {
        $user = User::factory()->create(['openid' => 'oTEST_OPENID']);

        $this->actingAs($user)
            ->putJson('/api/user', ['avatar' => 'not_a_url'])
            ->assertStatus(422);
    }
}
