<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserViewModalTest extends TestCase
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

    public function test_view_modal_renders_without_errors(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create([
            'nickname' => '详情渲染测试用户',
            'phone' => '13800001111',
        ]);

        Feedback::factory()->for($user)->create(['content' => '反馈内容甲']);
        $notification = Notification::factory()->create();
        $user->notifications()->attach($notification->id, ['read' => true, 'read_at' => now(), 'created_at' => now()]);
        $user->createToken('测试设备');
        AuditLog::create(['user_id' => $user->id, 'module' => 'user', 'type' => 'update', 'description' => '测试审计']);

        $user->refresh();

        $test = Livewire::test(ListUsers::class);
        // mountTableAction 会触发 view 弹窗（action-modal）组件的渲染快照，
        // 若 infolist 组件树（含四个关联 Tab 的 state 闭包）有任何 v5 不兼容，
        // 此处会抛出 ViewException。测试能在无异常下完成即证明详情弹窗可渲染。
        $test->mountTableAction('view', $user->getKey());

        $html = $test->html();

        // 列表页本身正常渲染（列名必然出现）
        $this->assertStringContainsString('昵称', $html);
    }

    public function test_view_action_exists_in_table(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListUsers::class)
            ->assertSuccessful()
            ->assertTableActionExists('view');
    }
}
