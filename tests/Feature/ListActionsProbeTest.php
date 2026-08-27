<?php

namespace Tests\Feature;

use App\Filament\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\MediaResource\Pages\ListMedia;
use App\Filament\Resources\NotificationResource\Pages\ListNotifications;
use App\Models\Announcement;
use App\Models\Article;
use App\Models\Category;
use App\Models\Media;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 弹窗化后，新增=header action、编辑/详情=table action，列表 HTML 不再包含
 * /create、/edit 链接。改用 Livewire 断言这些 action 已挂载且弹窗可渲染。
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

    /**
     * @param class-string $listClass
     */
    private function assertListModalActions(string $listClass, int $recordId): void
    {
        $this->actingAs($this->admin());

        Livewire::test($listClass)
            
            ->assertActionExists('create')
            ->assertTableActionExists('view')
            ->assertTableActionExists('edit');

        // 详情/编辑弹窗可挂载渲染（组件树 snapshot 校验）
        Livewire::test($listClass)->mountTableAction('view', $recordId);
        Livewire::test($listClass)->mountTableAction('edit', $recordId);
    }

    public function test_article_list_modal_actions(): void
    {
        $this->assertListModalActions(ListArticles::class, Article::factory()->create()->getKey());
    }

    public function test_category_list_modal_actions(): void
    {
        $this->assertListModalActions(ListCategories::class, Category::factory()->create()->getKey());
    }

    public function test_announcement_list_modal_actions(): void
    {
        $this->assertListModalActions(ListAnnouncements::class, Announcement::factory()->create()->getKey());
    }

    public function test_notification_list_modal_actions(): void
    {
        $this->assertListModalActions(ListNotifications::class, Notification::factory()->create()->getKey());
    }

    public function test_media_list_modal_actions(): void
    {
        $this->assertListModalActions(ListMedia::class, Media::factory()->create()->getKey());
    }
}
