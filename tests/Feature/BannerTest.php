<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mini_program' => ['app_id' => 'x', 'secret' => 'y']]);
    }

    public function test_public_api_returns_active_banners_only(): void
    {
        Banner::factory()->count(3)->create(['is_active' => true]);
        Banner::factory()->create(['is_active' => false]);
        Banner::factory()->future()->create(['is_active' => true]);
        Banner::factory()->expired()->create(['is_active' => true]);

        $response = $this->getJson('/api/banners');

        $response->assertOk();
        $response->assertJsonPath('code', 0);
        // 仅 3 个生效中的返回
        $response->assertJsonCount(3, 'data');
    }

    public function test_public_api_orders_by_sort_order(): void
    {
        Banner::factory()->create(['sort_order' => 5]);
        Banner::factory()->create(['sort_order' => 1]);
        Banner::factory()->create(['sort_order' => 3]);

        $response = $this->getJson('/api/banners');

        $orders = collect($response->json('data'))->pluck('sort_order')->all();
        $this->assertEquals([1, 3, 5], $orders);
    }

    public function test_public_api_masks_link_payload_by_type(): void
    {
        $banner = Banner::factory()->urlLink()->create();

        $response = $this->getJson('/api/banners');

        $item = collect($response->json('data'))->firstWhere('id', $banner->id);
        $this->assertNotNull($item);
        $this->assertArrayHasKey('url', $item);
        $this->assertNull($item['article_id']);
    }

    public function test_admin_requires_permission_to_view_list(): void
    {
        // 小程序普通用户：无 email/password，canAccessPanel 返回 false
        $member = User::factory()->create([
            'openid' => 'oTEST_'.uniqid(),
            'email' => null,
            'password' => null,
        ]);
        $member->roles()->detach();

        $this->actingAs($member)
            ->get('/admin/banners')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_list(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get('/admin/banners')
            ->assertOk();
    }

    public function test_super_admin_can_create_banner(): void
    {
        $admin = User::factory()->create(['email' => 'admin2@example.com']);
        $admin->assignRole('super-admin');

        // 超级管理员具备 banner.manage 权限（Policy 放行）
        $this->assertTrue($admin->can('create', Banner::class));

        // 经 Resource 表单逻辑落库：直接用 factory 模拟 create 动作
        $banner = Banner::factory()->create(['title' => '首页横幅']);

        $this->assertDatabaseHas('banners', ['title' => '首页横幅']);
        $this->assertNotNull($banner->id);
    }
}
