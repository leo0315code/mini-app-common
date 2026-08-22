<?php

namespace Tests\Feature;

use App\Filament\Widgets\OpexStatsWidget;
use App\Models\Feedback;
use App\Models\Role;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => '超级管理员']);
        $admin->assignRole($role->slug);

        return $admin;
    }

    /**
     * 工作台页面可渲染（运营 widget 已挂载），成员不可进入。
     */
    public function test_dashboard_accessible_and_role_guarded(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin')->assertOk();

        $member = User::factory()->create([
            'openid' => 'oTEST_' . uniqid(),
            'email' => null,
            'password' => null,
        ]);
        $this->actingAs($member)->get('/admin')->assertForbidden();
    }

    /**
     * OpexStatsWidget 产出 6 张运营指标卡，统计口径正确。
     * 说明：StatsOverviewWidget 为 Livewire 异步组件，初始 HTML 不含卡片文本，
     * 故直接对 getStats() 做单元级断言，锚定统计逻辑。
     */
    public function test_opex_stats_widget_structure(): void
    {
        Feedback::create([
            'type' => Feedback::TYPE_SUGGESTION,
            'content' => '建议内容',
            'status' => Feedback::STATUS_PENDING,
        ]);

        $widget = new OpexStatsWidget();
        $method = new ReflectionMethod(OpexStatsWidget::class, 'getStats');
        $method->setAccessible(true);
        $stats = $method->invoke($widget);

        $this->assertCount(6, $stats);
        foreach ($stats as $stat) {
            $this->assertInstanceOf(Stat::class, $stat);
        }

        $names = array_map(fn (Stat $s) => $s->getLabel(), $stats);
        foreach (['待处理反馈', '通知已读率', '内容总量', '媒体占用', '今日 API 调用', '封禁用户'] as $expected) {
            $this->assertContains($expected, $names, "缺少运营指标卡：{$expected}");
        }

        // 待处理反馈数应等于刚插入的 1 条 pending
        $pendingStat = $stats[array_search('待处理反馈', $names)];
        $this->assertSame(1, $pendingStat->getValue());
    }
}
