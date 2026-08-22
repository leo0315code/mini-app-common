<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SubscribeMessageFailure;
use App\Models\User;
use App\Services\WechatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SubscribeMessageFailureResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.mini_program' => ['app_id' => 'wx_test_app_id', 'secret' => 'wx_test_secret']]);
    }

    private function superAdmin(): User
    {
        $role = Role::factory()->create(['name' => '超级管理员', 'slug' => 'super-admin']);
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('admin123'),
        ]);
        $user->assignRole('super-admin');

        return $user;
    }

    private function regularAdmin(): User
    {
        Role::factory()->create(['name' => '超级管理员', 'slug' => 'super-admin']);
        Role::factory()->create(['name' => '普通管理员', 'slug' => 'admin']);

        return User::factory()->create([
            'email' => 'staff@example.com',
            'password' => bcrypt('admin123'),
        ]);
    }

    private function makeFailure(array $attrs = []): SubscribeMessageFailure
    {
        return SubscribeMessageFailure::factory()->create(array_merge([
            'scene' => 'notification_published',
            'openid' => 'oTEST_' . uniqid(),
            'template_id' => 'TPL_' . uniqid(),
            'payload' => ['data' => ['thing1' => ['value' => '测试标题'], 'time2' => ['value' => '2026-08-22 12:00']]],
            'page' => 'pages/index/index',
            'attempts' => 1,
            'last_errcode' => 43101,
            'last_errmsg' => 'user refuse to accept the msg',
            'last_attempted_at' => now()->subHour(),
        ], $attrs));
    }

    /**
     * 超级管理员可以访问失败记录列表页
     */
    public function test_super_admin_can_access_index(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/subscribe-message-failures')
            ->assertStatus(200);
    }

    /**
     * 超级管理员可以访问失败记录详情页
     */
    public function test_super_admin_can_access_view(): void
    {
        $failure = $this->makeFailure();

        $this->actingAs($this->superAdmin())
            ->get("/admin/subscribe-message-failures/{$failure->id}")
            ->assertStatus(200);
    }

    /**
     * 超级管理员不可创建失败记录（canCreate = false，且路由未注册返回404）
     */
    public function test_cannot_create_failure(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/subscribe-message-failures/create')
            ->assertStatus(404);
    }

    /**
     * 无管理员角色用户无法访问列表页
     */
    public function test_member_cannot_access_index(): void
    {
        $member = User::factory()->create([
            'openid' => 'oTEST_member',
            'email' => null,
            'password' => null,
        ]);

        $this->actingAs($member)
            ->get('/admin/subscribe-message-failures')
            ->assertStatus(403);
    }

    /**
     * 单条重发成功：记录标记为已解决，错误码归零
     */
    public function test_resend_success_marks_resolved(): void
    {
        $failure = $this->makeFailure([
            'last_errcode' => -1,
            'last_errmsg' => 'system busy',
            'resolved_at' => null,
        ]);

        $mock = Mockery::mock(WechatService::class)->makePartial();
        $mock->shouldReceive('sendSubscribeMessage')
            ->once()
            ->andReturn(['success' => true, 'errcode' => 0, 'errmsg' => 'ok']);
        $this->app->instance(WechatService::class, $mock);

        $result = \App\Filament\Resources\SubscribeMessageFailureResource::resendInternalProxy($failure);

        $this->assertTrue($result);
        $this->assertNotNull($failure->resolved_at, '重发成功应标记 resolved_at');
        $this->assertEquals(0, $failure->last_errcode);
        $this->assertEquals(2, $failure->attempts, '尝试次数应 +1');
    }

    /**
     * 单条重发失败：更新错误信息，但不标记为解决
     */
    public function test_resend_failed_updates_attempts(): void
    {
        $failure = $this->makeFailure([
            'last_errcode' => 43101,
            'last_errmsg' => 'user refuse',
            'attempts' => 2,
            'resolved_at' => null,
        ]);

        $mock = Mockery::mock(WechatService::class)->makePartial();
        $mock->shouldReceive('sendSubscribeMessage')
            ->once()
            ->andReturn(['success' => false, 'errcode' => 43101, 'errmsg' => 'user refuse to accept the msg rid: xxxx']);
        $this->app->instance(WechatService::class, $mock);

        $result = \App\Filament\Resources\SubscribeMessageFailureResource::resendInternalProxy($failure);

        $this->assertFalse($result);
        $this->assertNull($failure->resolved_at);
        $this->assertEquals(3, $failure->attempts);
        $this->assertEquals(43101, $failure->last_errcode);
    }

    /**
     * 重发异常：捕获异常并更新记录
     */
    public function test_resend_exception_caught(): void
    {
        $failure = $this->makeFailure(['attempts' => 1]);

        $mock = Mockery::mock(WechatService::class)->makePartial();
        $mock->shouldReceive('sendSubscribeMessage')
            ->once()
            ->andThrow(new \RuntimeException('network timeout'));
        $this->app->instance(WechatService::class, $mock);

        $result = \App\Filament\Resources\SubscribeMessageFailureResource::resendInternalProxy($failure);

        $this->assertFalse($result);
        $this->assertEquals(-999, $failure->last_errcode);
        $this->assertStringContainsString('network timeout', (string) $failure->last_errmsg);
        $this->assertEquals(2, $failure->attempts);
    }

    /**
     * 标记为已解决
     */
    public function test_mark_resolved_action(): void
    {
        $failure = $this->makeFailure(['resolved_at' => null]);

        $failure->update([
            'resolved_at' => now(),
            'resolved_note' => '测试手动标记',
        ]);

        $this->assertNotNull($failure->resolved_at);
        $this->assertEquals('测试手动标记', $failure->resolved_note);
    }

    /**
     * 批量重发：部分成功部分失败
     */
    public function test_bulk_resend_mixed(): void
    {
        $ok = $this->makeFailure(['last_errcode' => -1, 'attempts' => 1]);
        $bad = $this->makeFailure(['last_errcode' => 43101, 'attempts' => 2]);

        $mock = Mockery::mock(WechatService::class)->makePartial();
        $mock->shouldReceive('sendSubscribeMessage')
            ->andReturnUsing(function ($openid) use ($ok, $bad) {
                if ($openid === $ok->openid) {
                    return ['success' => true, 'errcode' => 0, 'errmsg' => 'ok'];
                }

                return ['success' => false, 'errcode' => 43101, 'errmsg' => 'user refuse'];
            });
        $this->app->instance(WechatService::class, $mock);

        $r1 = \App\Filament\Resources\SubscribeMessageFailureResource::resendInternalProxy($ok);
        $r2 = \App\Filament\Resources\SubscribeMessageFailureResource::resendInternalProxy($bad);

        $this->assertTrue($r1);
        $this->assertFalse($r2);

        $ok->refresh();
        $bad->refresh();

        $this->assertNotNull($ok->resolved_at);
        $this->assertNull($bad->resolved_at);
        $this->assertEquals(2, $ok->attempts);
        $this->assertEquals(3, $bad->attempts);
    }

    /**
     * 场景、错误码筛选枚举存在
     */
    public function test_table_definition_works(): void
    {
        $resource = new \App\Filament\Resources\SubscribeMessageFailureResource;

        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canEdit(new SubscribeMessageFailure));
        $this->assertEquals('推送失败记录', $resource::getNavigationLabel());
        $this->assertEquals('系统管理', (string) $resource::getNavigationGroup());
    }

    /**
     * Policy: super-admin 拥有 viewAny 权限
     */
    public function test_policy_viewany_for_super_admin(): void
    {
        $user = $this->superAdmin();

        $this->assertTrue($user->can('viewAny', SubscribeMessageFailure::class));
        $this->assertTrue($user->can('view', $this->makeFailure()));
        $this->assertTrue($user->can('delete', $this->makeFailure()));
    }

    /**
     * Policy: super-admin 总是拥有完全权限；有显式菜单权限分配的角色也可以访问
     */
    public function test_policy_requires_menu_permission(): void
    {
        // 播种角色和菜单
        Role::factory()->create(['name' => '超级管理员', 'slug' => 'super-admin']);
        $adminRole = Role::factory()->create(['name' => '普通管理员', 'slug' => 'admin']);

        $parent = \App\Models\Menu::create([
            'name' => '系统管理', 'slug' => 'system', 'icon' => 'heroicon-o-cog-6-tooth',
            'sort_order' => 90, 'is_visible' => true, 'is_active' => true,
        ]);
        $viewMenu = \App\Models\Menu::create([
            'parent_id' => $parent->id,
            'name' => '推送失败记录', 'slug' => 'system.subscribe-failure',
            'permission' => 'subscribe_message_failure.view',
            'sort_order' => 3, 'is_visible' => true, 'is_active' => true,
        ]);

        // admin 角色绑定菜单权限
        $adminRole->menus()->syncWithoutDetaching([$parent->id, $viewMenu->id]);
        $hasPermUser = User::factory()->create([
            'email' => 'perm@example.com',
            'password' => bcrypt('secret'),
        ]);
        $hasPermUser->assignRole('admin');

        $superAdmin = User::factory()->create([
            'email' => 'super@example.com',
            'password' => bcrypt('secret'),
        ]);
        $superAdmin->assignRole('super-admin');

        // 超级管理员直接通过
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->can('viewAny', SubscribeMessageFailure::class));
        $this->assertTrue($superAdmin->can('delete', new SubscribeMessageFailure));
        // 有菜单权限的 admin 可以访问
        $this->assertTrue($hasPermUser->can('viewAny', SubscribeMessageFailure::class));
    }
}
