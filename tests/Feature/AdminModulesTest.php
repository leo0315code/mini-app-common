<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminModulesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    private function member(): User
    {
        // 小程序普通用户：无 email/password（仅 openid）
        return User::factory()->create([
            'openid' => 'oTEST_' . uniqid(),
            'email' => null,
            'password' => null,
        ]);
    }

    /**
     * 角色指派：为管理员绑定 super-admin 后，hasRole 为真。
     */
    public function test_assign_role_and_has_role(): void
    {
        $role = Role::factory()->create(['name' => '超级管理员', 'slug' => 'super-admin']);
        $user = $this->admin();

        $user->assignRole('super-admin');

        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertTrue($user->isSuperAdmin());
        $this->assertEquals(1, $user->roles()->count());
        $this->assertDatabaseHas('role_user', [
            'role_id' => $role->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * canAccessPanel：有 email+password 且有 admin 角色才可访问后台；
     * 首次部署 roles 表为空时放行（安装阶段安全）。
     */
    public function test_can_access_panel_requires_role(): void
    {
        // roles 表未播种时，仍按旧规则（email+password）放行
        $plainAdmin = $this->admin();
        $this->assertTrue($plainAdmin->canAccessPanel(app(\Filament\Panel::class)));

        // 播种角色后，无角色者被拒
        Role::factory()->create(['slug' => 'admin']);
        $this->assertFalse($plainAdmin->canAccessPanel(app(\Filament\Panel::class)));

        // 绑定 admin 后放行
        $plainAdmin->assignRole('admin');
        $this->assertTrue($plainAdmin->canAccessPanel(app(\Filament\Panel::class)));
    }

    /**
     * 普通小程序用户（无 email/password）不可访问后台。
     */
    public function test_member_cannot_access_panel(): void
    {
        $member = $this->member();
        $this->assertFalse($member->canAccessPanel(app(\Filament\Panel::class)));
    }

    /**
     * 通知群发：scope=all 时展开为全量用户回执，用户可见。
     */
    public function test_notification_broadcast_all_and_read(): void
    {
        $sender = $this->admin();
        $member = $this->member();

        $notification = Notification::factory()->create([
            'creator_id' => $sender->id,
            'scope' => 'all',
            'published' => true,
        ]);
        $notification->dispatchToRecipients();

        // 该成员有未读回执
        $this->assertDatabaseHas('notification_user', [
            'notification_id' => $notification->id,
            'user_id' => $member->id,
            'read' => false,
        ]);
        // scope=all 时所有用户（含发送者）均收到回执
        $this->assertEquals(User::count(), $notification->recipients()->count());

        // 小程序端拉取 + 未读数
        $resp = $this->actingAs($member)
            ->getJson('/api/notifications')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->json();

        $this->assertEquals(1, $resp['data']['unread_count']);
        $item = $resp['data']['items'][0];
        $this->assertEquals($notification->id, $item['id']);
        $this->assertFalse($item['read']);

        // 标记已读
        $this->actingAs($member)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(200)
            ->assertJsonPath('code', 0);

        $this->assertDatabaseHas('notification_user', [
            'notification_id' => $notification->id,
            'user_id' => $member->id,
            'read' => true,
        ]);
    }

    /**
     * 通知指定用户：仅目标用户收到。
     */
    public function test_notification_scope_specified(): void
    {
        $member = $this->member();
        $other = $this->member();

        $notification = Notification::factory()->create([
            'scope' => 'specified',
            'targets' => [$member->id],
            'published' => true,
        ]);
        $notification->dispatchToRecipients();

        $this->assertEquals(1, $notification->recipients()->count());
        $this->assertTrue($notification->recipients()->where('user_id', $member->id)->exists());
        $this->assertFalse($notification->recipients()->where('user_id', $other->id)->exists());
    }

    /**
     * 媒体上传：登录用户上传文件返回可访问 URL，并落库。
     */
    public function test_media_upload_requires_auth_and_stores(): void
    {
        Storage::fake('public');

        // 未登录应 401
        $this->postJson('/api/upload', [
            'file' => UploadedFile::fake()->image('test.jpg'),
        ])->assertStatus(401);

        $user = $this->member();
        $resp = $this->actingAs($user)
            ->postJson('/api/upload', [
                'file' => UploadedFile::fake()->image('test.jpg'),
                'collection' => 'avatar',
            ])
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->json();

        $this->assertStringContainsString('/storage/', $resp['data']['url']);
        $this->assertDatabaseHas('media', [
            'user_id' => $user->id,
            'collection' => 'avatar',
        ]);
    }

    /**
     * 媒体上传校验：缺文件返回 422。
     */
    public function test_media_upload_validation(): void
    {
        Storage::fake('public');
        $user = $this->member();

        $this->actingAs($user)
            ->postJson('/api/upload', [])
            ->assertStatus(422);
    }
}
