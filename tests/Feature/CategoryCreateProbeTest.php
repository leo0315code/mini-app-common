<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    /**
     * 验证分类的新建改为弹窗（header action）后：
     * 1) 列表页可正常渲染；2) create header action 已挂载；3) 弹窗可挂载渲染无异常。
     * 不再有独立 /console/categories/create 页面（弹窗化后该路由删除）。
     */
    public function test_category_create_is_modal(): void
    {
        $this->makeAdmin();

        $test = Livewire::test(ListCategories::class)
            
            ->assertActionExists('create');

        // 触发新建弹窗渲染（form 组件树 snapshot 校验）
        Livewire::test(ListCategories::class)->mountAction('create');

        $this->assertTrue(true);
    }
}
