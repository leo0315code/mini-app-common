<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MenuPermissionManager
{
    protected string $cachePrefix = 'user_permissions_';

    /**
     * 缓存版本键：清除全部用户权限缓存时递增版本号，
     * 旧键随 TTL 自然过期。避免使用 Cache::tags()——
     * Laravel 10+ 的 Redis/Database 缓存驱动不再支持标签，线上会直接抛
     * BadMethodCallException（this cache store does not support tagging）。
     */
    protected string $versionKey = 'user_permissions_version';

    protected int $cacheTtl = 3600;

    public function getUserPermissions(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return ['*'];
        }

        $hasTable = $this->tablesReady();

        if (! $hasTable) {
            return ['*'];
        }

        $roleCount = $user->roles()->count();

        if ($roleCount === 0) {
            return ['*'];
        }

        return Cache::remember(
            $this->userCacheKey($user->id),
            $this->cacheTtl,
            function () use ($user) {
                return $user->roles()
                    ->with('menus')
                    ->get()
                    ->flatMap(fn ($role) => $role->menus)
                    ->pluck('permission')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            },
        );
    }

    public function hasPermission(User $user, string $permission): bool
    {
        $permissions = $this->getUserPermissions($user);

        if (in_array('*', $permissions)) {
            return true;
        }

        if (in_array($permission, $permissions)) {
            return true;
        }

        $prefix = rtrim($permission, '.').'.';
        foreach ($permissions as $p) {
            if (str_starts_with($p, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function getUserMenuSlugs(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return ['*'];
        }

        $hasTable = $this->tablesReady();

        if (! $hasTable) {
            return ['*'];
        }

        $roleCount = $user->roles()->count();

        if ($roleCount === 0) {
            return ['*'];
        }

        return Cache::remember(
            $this->userCacheKey('slugs_'.$user->id),
            $this->cacheTtl,
            function () use ($user) {
                return $user->roles()
                    ->with('menus')
                    ->get()
                    ->flatMap(fn ($role) => $role->menus)
                    ->pluck('slug')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            },
        );
    }

    public function canAccessMenu(User $user, string $menuSlug): bool
    {
        $slugs = $this->getUserMenuSlugs($user);

        if (in_array('*', $slugs)) {
            return true;
        }

        return in_array($menuSlug, $slugs);
    }

    public function clearUserCache(User $user): void
    {
        Cache::forget($this->userCacheKey($user->id));
        Cache::forget($this->userCacheKey('slugs_'.$user->id));
    }

    public function clearAllCache(): void
    {
        // 递增版本号使全部旧键失效（旧键随 TTL 自然过期），兼容不支持标签的缓存驱动
        Cache::forever($this->versionKey, $this->currentVersion() + 1);
        app(MenuCascadeService::class)->clearCache();
    }

    protected function userCacheKey(string $suffix): string
    {
        return $this->cachePrefix.$this->currentVersion().'_'.$suffix;
    }

    protected function currentVersion(): int
    {
        return (int) Cache::get($this->versionKey, 0);
    }

    protected function tablesReady(): bool
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $cached = Schema::hasTable('roles')
            && Schema::hasTable('menus')
            && Schema::hasTable('menu_role');

        return $cached;
    }
}