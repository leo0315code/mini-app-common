<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Support\MenuCascadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MenuCascadeService::ensureCascadeConsistency 优化回归：
 *  - 循环内「逐条 where('id')->value('parent_id')」已改为「一次性 pluck parent_id 映射」
 *  - 行为不变：父菜单未勾选时，子菜单不应被保留。
 */
class MenuCascadeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeMenu(?int $parentId, bool $active = true): Menu
    {
        return Menu::query()->create([
            'parent_id' => $parentId,
            'name' => 'menu-'.uniqid(),
            'slug' => 'menu-'.uniqid(),
            'is_active' => $active,
            'is_visible' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_drop_child_when_parent_not_selected(): void
    {
        $parent = $this->makeMenu(null);
        $child = $this->makeMenu($parent->id);

        $service = app(MenuCascadeService::class);
        $result = $service->ensureCascadeConsistency([$child->id]);

        // 子菜单的父未被勾选 → 子菜单应被剔除
        $this->assertNotContains($child->id, $result);
        $this->assertEmpty($result);
    }

    public function test_keep_child_when_parent_selected(): void
    {
        $parent = $this->makeMenu(null);
        $child = $this->makeMenu($parent->id);

        $service = app(MenuCascadeService::class);
        $result = $service->ensureCascadeConsistency([$parent->id, $child->id]);

        $this->assertContains($parent->id, $result);
        $this->assertContains($child->id, $result);
    }

    public function test_parent_selected_pulls_descendants(): void
    {
        $grand = $this->makeMenu(null);
        $parent = $this->makeMenu($grand->id);
        $child = $this->makeMenu($parent->id);

        $service = app(MenuCascadeService::class);
        $result = $service->ensureCascadeConsistency([$grand->id]);

        // 勾选祖父 → 子孙一并保留
        $this->assertContains($grand->id, $result);
        $this->assertContains($parent->id, $result);
        $this->assertContains($child->id, $result);
    }

    public function test_empty_input_returns_empty(): void
    {
        $service = app(MenuCascadeService::class);
        $this->assertSame([], $service->ensureCascadeConsistency([]));
    }
}
