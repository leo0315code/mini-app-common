<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\Role;

class RolePresetTemplates
{
    public static function all(): array
    {
        return [
            'content_editor' => [
                'name' => '内容编辑',
                'slug' => 'content-editor',
                'description' => '可管理文章、分类及反馈，不可修改系统设置',
                'permissions' => [
                    'article.view', 'article.manage',
                    'category.view', 'category.manage',
                    'feedback.view', 'feedback.manage',
                    'announcement.view',
                ],
            ],
            'operator' => [
                'name' => '运营',
                'slug' => 'operator',
                'description' => '内容编辑+公告管理+用户查看',
                'permissions' => [
                    'article.view', 'article.manage',
                    'category.view', 'category.manage',
                    'feedback.view', 'feedback.manage',
                    'announcement.view', 'announcement.manage',
                    'user.view',
                    'media.view',
                ],
            ],
            'reviewer' => [
                'name' => '审核员',
                'slug' => 'reviewer',
                'description' => '只读审核权限，不可修改任何数据',
                'permissions' => [
                    'article.view',
                    'category.view',
                    'feedback.view',
                    'announcement.view',
                    'user.view',
                    'audit-log.view',
                ],
            ],
            'admin' => [
                'name' => '系统管理员',
                'slug' => 'admin',
                'description' => '除超级管理员外的最高权限，可管理所有模块',
                'permissions' => [
                    'dashboard.view',
                    'article.view', 'article.manage',
                    'category.view', 'category.manage',
                    'user.view', 'user.manage',
                    'role.view',
                    'menu.view',
                    'feedback.view', 'feedback.manage',
                    'announcement.view', 'announcement.manage',
                    'audit-log.view',
                    'media.view', 'media.manage',
                ],
            ],
        ];
    }

    public static function applyPreset(Role $role, string $presetKey): bool
    {
        $presets = static::all();
        if (! isset($presets[$presetKey])) {
            return false;
        }

        $preset = $presets[$presetKey];

        $menuIds = Menu::query()
            ->whereIn('permission', $preset['permissions'])
            ->pluck('id')
            ->toArray();

        $role->menus()->sync($menuIds);

        app(MenuPermissionManager::class)->clearUserCache($role);

        return true;
    }

    public static function createFromPreset(string $presetKey, array $overrides = []): ?Role
    {
        $presets = static::all();
        if (! isset($presets[$presetKey])) {
            return null;
        }

        $preset = $presets[$presetKey];

        $role = Role::create([
            'name' => $overrides['name'] ?? $preset['name'],
            'slug' => $overrides['slug'] ?? $preset['slug'].'_'.time(),
            'description' => $overrides['description'] ?? $preset['description'],
        ]);

        $menuIds = Menu::query()
            ->whereIn('permission', $preset['permissions'])
            ->pluck('id')
            ->toArray();

        $role->menus()->sync($menuIds);

        app(MenuPermissionManager::class)->clearUserCache($role);

        return $role;
    }

    public static function getPresetPermissions(string $presetKey): array
    {
        $presets = static::all();

        return $presets[$presetKey]['permissions'] ?? [];
    }

    public static function getMenuIdsForPermissions(array $permissions): array
    {
        return Menu::query()
            ->whereIn('permission', $permissions)
            ->pluck('id')
            ->toArray();
    }
}
