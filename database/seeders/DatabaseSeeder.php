<?php

namespace Database\Seeders;

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
        // 创建管理员账号
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '管理员',
                'password' => Hash::make('admin123'),
            ]
        );

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
    }
}
