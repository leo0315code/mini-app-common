<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Notification;
use App\Models\Role;
use App\Models\SubscribeMessageFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ResourcesViewRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): User
    {
        Role::factory()->create(['slug' => 'super-admin', 'name' => '超级管理员']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_all_view_pages_render(): void
    {
        $this->actingAsAdmin();

        $cases = [
            'console/announcements' => fn () => Announcement::factory()->create(),
            'console/articles' => fn () => Article::factory()->create(),
            'console/banners' => fn () => Banner::factory()->create(),
            'console/categories' => fn () => Category::factory()->create(),
            'console/media' => fn () => Media::factory()->create(),
            'console/menus' => fn () => Menu::create(['name' => '测试菜单', 'slug' => 'test-menu-' . uniqid()]),
            'console/notifications' => fn () => Notification::factory()->create(),
            'console/roles' => fn () => Role::factory()->create(),
            'console/tokens' => fn () => User::factory()->create()->createToken('t')->accessToken,
            'console/users' => fn () => User::factory()->create(),
            'console/audit-logs' => fn () => AuditLog::query()->create(['user_id' => User::factory()->create()->id, 'module' => 'user', 'type' => 'update', 'description' => 'x']),
            'console/feedback' => fn () => Feedback::factory()->create(),
            'console/subscribe-message-failures' => fn () => SubscribeMessageFailure::factory()->create(),
        ];

        foreach ($cases as $base => $factory) {
            $record = $factory();
            $id = $record instanceof PersonalAccessToken ? $record->getKey() : $record->getKey();
            // 刷新让关联计数等闭包可正常取数
            if (method_exists($record, 'refresh')) {
                $record->refresh();
            }
            $url = "/{$base}/{$id}";
            $resp = $this->get($url);
            $this->assertSame(200, $resp->getStatusCode(), "FAIL render {$url}");
            // 取响应文本，确认含详情标记（任意字段 label）
            $text = $resp->getContent();
            $this->assertNotEmpty($text, "empty body {$url}");
        }

        $this->assertTrue(true);
    }
}
