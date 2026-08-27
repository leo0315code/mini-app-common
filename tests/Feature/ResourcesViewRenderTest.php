<?php

namespace Tests\Feature;

use App\Filament\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Filament\Resources\BannerResource\Pages\ListBanners;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\FeedbackResource\Pages\ListFeedback;
use App\Filament\Resources\MediaResource\Pages\ListMedia;
use App\Filament\Resources\MenuResource\Pages\ListMenus;
use App\Filament\Resources\NotificationResource\Pages\ListNotifications;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\SubscribeMessageFailureResource\Pages\ListSubscribeMessageFailures;
use App\Filament\Resources\TokenResource\Pages\ListTokens;
use App\Filament\Resources\UserResource\Pages\ListUsers;
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
use Livewire\Livewire;
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

    /**
     * 全部 13 个 Resource 的列表页 + 详情弹窗(view table action) 渲染零异常。
     * 同时校验 create header action 与 edit/view table action 已挂载（弹窗化标志）。
     */
    public function test_all_resources_modalized(): void
    {
        $this->actingAsAdmin();

        $cases = [
            'announcements' => [ListAnnouncements::class, fn () => Announcement::factory()->create(), true],
            'articles' => [ListArticles::class, fn () => Article::factory()->create(), true],
            'audit-logs' => [ListAuditLogs::class, fn () => AuditLog::query()->create(['user_id' => User::factory()->create()->id, 'module' => 'user', 'type' => 'update', 'description' => 'x']), false],
            'banners' => [ListBanners::class, fn () => Banner::factory()->create(), true],
            'categories' => [ListCategories::class, fn () => Category::factory()->create(), true],
            'feedback' => [ListFeedback::class, fn () => Feedback::factory()->create(), false],
            'media' => [ListMedia::class, fn () => Media::factory()->create(), true],
            'menus' => [ListMenus::class, fn () => Menu::create(['name' => '测试菜单', 'slug' => 'tm-' . uniqid()]), true],
            'notifications' => [ListNotifications::class, fn () => Notification::factory()->create(), true],
            'roles' => [ListRoles::class, fn () => Role::factory()->create(), true],
            'subscribe-message-failures' => [ListSubscribeMessageFailures::class, fn () => SubscribeMessageFailure::factory()->create(), false],
            'tokens' => [ListTokens::class, fn () => User::factory()->create()->createToken('t')->accessToken, false],
            'users' => [ListUsers::class, fn () => User::factory()->create(), true],
        ];

        foreach ($cases as $key => [$listClass, $factory, $creatable]) {
            $record = $factory();
            $id = $record->getKey();

            $test = Livewire::test($listClass)
                
                ->assertTableActionExists('view');

            if ($creatable) {
                $test->assertTableActionExists('edit');
                $test->assertActionExists('create');
            }

            // 触发详情弹窗渲染（infolist 组件树会在此 snapshot 校验，任何闭包错误都会抛出）
            Livewire::test($listClass)->mountTableAction('view', $id);

            // 编辑弹窗渲染（form 组件树校验）
            if ($creatable) {
                Livewire::test($listClass)->mountTableAction('edit', $id);
            }
        }

        $this->assertTrue(true);
    }
}
