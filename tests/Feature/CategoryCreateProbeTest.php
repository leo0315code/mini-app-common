<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCreateProbeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(): User
    {
        Role::factory()->create(['slug' => 'super-admin', 'name' => '超级管理员']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_create_routes_resolve_not_404(): void
    {
        $this->makeAdmin();
        $this->withoutExceptionHandling();

        // 含 create 页的资源：create 必须 200，不能因 /{record} 抢占而 404
        $creatable = [
            'console/announcements' => fn () => Announcement::factory()->create(),
            'console/articles' => fn () => Article::factory()->create(),
            'console/banners' => fn () => Banner::factory()->create(),
            'console/categories' => fn () => Category::factory()->create(),
            'console/media' => fn () => Media::factory()->create(),
            'console/menus' => fn () => Menu::create(['name' => '测试菜单', 'slug' => 'tm-' . uniqid()]),
            'console/notifications' => fn () => Notification::factory()->create(),
            'console/roles' => fn () => Role::factory()->create(),
        ];

        foreach ($creatable as $base => $factory) {
            $factory();
            $resp = $this->get("/{$base}/create");
            if ($resp->getStatusCode() !== 200) {
                fwrite(STDERR, "\n[probe][{$base}/create] status={$resp->getStatusCode()}\n");
                fwrite(STDERR, "[probe][body] " . strip_tags(substr($resp->getContent(), 0, 600)) . "\n");
            }
            $this->assertSame(
                200,
                $resp->getStatusCode(),
                "FAIL create {$base}/create (应为 200, 误判为 404 说明 /{record} 抢占路由)"
            );
        }

        $this->assertTrue(true);
    }
}
