<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MenuPermissionManager
{
    protected string $cachePrefix = 'user_permissions_';

    protected string $cacheTag = 'user_permissions';

    protected string $cascadeCacheTag = 'menu_cascade';

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

        return Cache::tags($this->cacheTag)->remember(
            $this->cachePrefix.$user->id,
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

        return Cache::tags($this->cacheTag)->remember(
            $this->cachePrefix.'slugs_'.$user->id,
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
        Cache::forget($this->cachePrefix.$user->id);
        Cache::forget($this->cachePrefix.'slugs_'.$user->id);
    }

    public function clearAllCache(): void
    {
        Cache::tags($this->cacheTag)->flush();
        Cache::tags($this->cascadeCacheTag)->flush();
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