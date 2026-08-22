<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class MenusTableSeeder extends Seeder
{
    public function run(): void
    {
        $isProd = App::environment('production');
        $hasMenus = Menu::count() > 0;

        if ($isProd && $hasMenus) {
            echo "⚠️  生产环境检测到已有菜单数据，跳过 truncate\n";

            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        Menu::truncate();
        DB::table('menu_role')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->seedMenus();
    }

    protected function seedMenus(): void
    {
        $dashboard = Menu::create([
            'name' => '仪表盘',
            'slug' => 'dashboard',
            'icon' => 'heroicon-o-home',
            'route' => 'filament.pages.dashboard',
            'permission' => 'dashboard.view',
            'sort_order' => 1,
            'is_visible' => true,
            'is_active' => true,
        ]);

        $article = Menu::create([
            'name' => '文章管理',
            'slug' => 'article',
            'icon' => 'heroicon-o-document-text',
            'sort_order' => 10,
            'is_visible' => true,
            'is_active' => true,
        ]);

        Menu::create(['parent_id' => $article->id, 'name' => '文章列表', 'slug' => 'article.list', 'route' => 'filament.resources.articles.index', 'permission' => 'article.view', 'sort_order' => 1, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['parent_id' => $article->id, 'name' => '文章管理', 'slug' => 'article.manage', 'permission' => 'article.manage', 'sort_order' => 2, 'is_visible' => false, 'is_active' => true]);
        Menu::create(['parent_id' => $article->id, 'name' => '分类管理', 'slug' => 'article.category', 'route' => 'filament.resources.categories.index', 'permission' => 'category.view', 'sort_order' => 3, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['parent_id' => $article->id, 'name' => '分类管理权限', 'slug' => 'category.manage', 'permission' => 'category.manage', 'sort_order' => 4, 'is_visible' => false, 'is_active' => true]);

        $user = Menu::create([
            'name' => '用户管理',
            'slug' => 'user',
            'icon' => 'heroicon-o-user-group',
            'sort_order' => 20,
            'is_visible' => true,
            'is_active' => true,
        ]);

        Menu::create(['parent_id' => $user->id, 'name' => '用户列表', 'slug' => 'user.list', 'route' => 'filament.resources.users.index', 'permission' => 'user.view', 'sort_order' => 1, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['parent_id' => $user->id, 'name' => '用户管理权限', 'slug' => 'user.manage', 'permission' => 'user.manage', 'sort_order' => 2, 'is_visible' => false, 'is_active' => true]);
        Menu::create(['parent_id' => $user->id, 'name' => '角色管理', 'slug' => 'user.role', 'route' => 'filament.resources.roles.index', 'permission' => 'role.view', 'sort_order' => 3, 'is_visible' => true, 'is_active' => true]);

        $system = Menu::create([
            'name' => '系统管理',
            'slug' => 'system',
            'icon' => 'heroicon-o-cog-6-tooth',
            'sort_order' => 90,
            'is_visible' => true,
            'is_active' => true,
        ]);

        Menu::create(['parent_id' => $system->id, 'name' => '角色管理', 'slug' => 'system.role', 'route' => 'filament.resources.roles.index', 'permission' => 'role.manage', 'sort_order' => 1, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['parent_id' => $system->id, 'name' => '菜单管理', 'slug' => 'system.menu', 'route' => 'filament.resources.menus.index', 'permission' => 'menu.manage', 'sort_order' => 2, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['parent_id' => $system->id, 'name' => '推送失败记录', 'slug' => 'system.subscribe-failure', 'route' => 'filament.resources.subscribe-message-failures.index', 'permission' => 'subscribe_message_failure.view', 'sort_order' => 3, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['parent_id' => $system->id, 'name' => '推送失败记录管理', 'slug' => 'subscribe_message_failure.manage', 'permission' => 'subscribe_message_failure.manage', 'sort_order' => 4, 'is_visible' => false, 'is_active' => true]);

        Menu::create(['name' => '反馈管理', 'slug' => 'feedback', 'icon' => 'heroicon-o-chat-bubble-left-right', 'route' => 'filament.resources.feedback.index', 'permission' => 'feedback.view', 'sort_order' => 30, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['name' => '反馈管理权限', 'slug' => 'feedback.manage', 'permission' => 'feedback.manage', 'sort_order' => 31, 'is_visible' => false, 'is_active' => true]);
        Menu::create(['name' => '公告管理', 'slug' => 'announcement', 'icon' => 'heroicon-o-megaphone', 'route' => 'filament.resources.announcements.index', 'permission' => 'announcement.view', 'sort_order' => 40, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['name' => '公告管理权限', 'slug' => 'announcement.manage', 'permission' => 'announcement.manage', 'sort_order' => 41, 'is_visible' => false, 'is_active' => true]);
        Menu::create(['name' => '审计日志', 'slug' => 'audit-log', 'icon' => 'heroicon-o-clock', 'route' => 'filament.resources.audit-logs.index', 'permission' => 'audit-log.view', 'sort_order' => 50, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['name' => '媒体管理', 'slug' => 'media', 'icon' => 'heroicon-o-photo', 'route' => 'filament.resources.media.index', 'permission' => 'media.view', 'sort_order' => 60, 'is_visible' => true, 'is_active' => true]);
        Menu::create(['name' => '媒体管理权限', 'slug' => 'media.manage', 'permission' => 'media.manage', 'sort_order' => 61, 'is_visible' => false, 'is_active' => true]);
        Menu::create(['name' => '系统设置', 'slug' => 'settings', 'icon' => 'heroicon-o-squares-2x2', 'route' => 'filament.resources.settings.index', 'permission' => 'settings.view', 'sort_order' => 70, 'is_visible' => true, 'is_active' => true]);

        $superAdmin = Role::where('slug', 'super-admin')->first();
        if ($superAdmin) {
            // Menu::created 事件已自动分配新菜单给 super-admin
            // 此处确保所有菜单都被分配（兼容 created 事件可能未触发的场景）
            $menuIds = Menu::all()->pluck('id')->toArray();
            $existingIds = $superAdmin->menus()->pluck('menu_id')->toArray();
            $missingIds = array_diff($menuIds, $existingIds);
            if (! empty($missingIds)) {
                $superAdmin->menus()->attach($missingIds);
                echo "已补充分配 " . count($missingIds) . " 个菜单权限给超级管理员\n";
            } else {
                echo "超级管理员已拥有全部菜单权限\n";
            }
        }

        echo "菜单初始化完成，共 " . Menu::count() . " 条记录\n";
    }
}