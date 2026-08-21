<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 创建默认角色（轻量 RBAC）
        $roles = [
            ['name' => '超级管理员', 'slug' => 'super-admin', 'description' => '拥有后台全部权限，可越过资源级限制'],
            ['name' => '管理员', 'slug' => 'admin', 'description' => '可访问与配置大部分后台功能'],
            ['name' => '编辑', 'slug' => 'editor', 'description' => '可管理内容（公告、通知、反馈）'],
            ['name' => '访客', 'slug' => 'viewer', 'description' => '仅查看统计与只读资源'],
        ];
        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        // 创建管理员账号
        $admin = User::firstOrCreate(
            ['email' => '453507012@qq.com'],
            [
                'name' => '管理员',
                'password' => Hash::make('admin123'),
            ]
        );
        $admin->assignRole('super-admin');

        // 创建测试小程序用户
        $avatars = [
            'https://api.dicebear.com/7.x/adventurer/svg?seed=Felix',
            'https://api.dicebear.com/7.x/adventurer/svg?seed=Aneka',
            'https://api.dicebear.com/7.x/adventurer/svg?seed=Bailey',
            'https://api.dicebear.com/7.x/adventurer/svg?seed=Cali',
            'https://api.dicebear.com/7.x/adventurer/svg?seed=Dusty',
        ];

        $nicknames = ['微信用户', '小程序用户', '测试用户', '开发者', '运营人员', '产品经理', '设计师', '前端工程师'];
        $phones = ['13800138001', '13900139002', '15012345678', '18612345678', null, null, null, null];

        for ($i = 0; $i < 20; $i++) {
            User::firstOrCreate(
                ['openid' => 'o_test_' . Str::random(20)],
                [
                    'name' => null,
                    'nickname' => $nicknames[array_rand($nicknames)] . '_' . ($i + 1),
                    'avatar' => $avatars[array_rand($avatars)],
                    'gender' => rand(0, 2),
                    'phone' => $phones[array_rand($phones)],
                    'email' => null,
                    'password' => null,
                    'meta' => ['source' => 'wechat', 'version' => '1.0'],
                    'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                ]
            );
        }

        // 内容分类与示例文章（CMS）
        $categories = [
            ['name' => '帮助中心', 'slug' => 'help', 'description' => '使用教程与常见问题', 'sort' => 10],
            ['name' => '平台公告', 'slug' => 'platform', 'description' => '平台动态与版本资讯', 'sort' => 20],
            ['name' => '活动专区', 'slug' => 'activity', 'description' => '运营活动与福利', 'sort' => 30],
        ];
        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }

        if (Article::query()->doesntExist()) {
            $help = Category::where('slug', 'help')->first();
            Article::factory()->count(2)->state([
                'category_id' => $help?->id,
                'title' => '新手入门指南',
                'summary' => '三步完成小程序绑定与基础设置。',
            ])->create(['created_by' => $admin->id]);

            $platform = Category::where('slug', 'platform')->first();
            Article::factory()->state([
                'category_id' => $platform?->id,
                'title' => 'v1.7.0 内容中心上线',
                'is_top' => true,
            ])->create(['created_by' => $admin->id]);
        }
    }
}
