<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 探测各业务资源列表页是否渲染出「新增」与「编辑」入口，
 * 用以验证 Filament v5 Section 命名空间修复后 Create/Edit action 是否正常。
 */
class ListActionsProbeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create([
            'email' => 'probe_admin@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole('super-admin');

        return $user;
    }

    private function assertListHasCreateAndEdit(string $listUrl, string $createUrl): void
    {
        $html = $this->actingAs($this->admin())
            ->get($listUrl)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($createUrl, $html, "列表页缺少「新增」入口：{$listUrl}");
        $this->assertMatchesRegularExpression('#/console/[^/]+/\d+/edit#', $html, "列表页缺少「编辑」入口：{$listUrl}");
    }

    public function test_article_list_has_create_and_edit(): void
    {
        Article::factory()->count(2)->create();
        $this->assertListHasCreateAndEdit('/console/articles', '/console/articles/create');
    }

    public function test_category_list_has_create_and_edit(): void
    {
        Category::factory()->count(2)->create();
        $this->assertListHasCreateAndEdit('/console/categories', '/console/categories/create');
    }

    public function test_announcement_list_has_create_and_edit(): void
    {
        Announcement::factory()->count(2)->create();
        $this->assertListHasCreateAndEdit('/console/announcements', '/console/announcements/create');
    }

    public function test_notification_list_has_create_and_edit(): void
    {
        Notification::factory()->count(2)->create();
        $this->assertListHasCreateAndEdit('/console/notifications', '/console/notifications/create');
    }

    public function test_media_list_has_create_and_edit(): void
    {
        Media::factory()->count(2)->create();
        $this->assertListHasCreateAndEdit('/console/media', '/console/media/create');
    }
}
