<?php

namespace App\Observers;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Support\MenuCascadeService;
use App\Support\MenuPermissionManager;

class PermissionCacheObserver
{
    public function created(object $model): void
    {
        if ($model instanceof User) {
            return;
        }

        $this->clearAllCaches();
    }

    public function updated(object $model): void
    {
        if ($model instanceof User) {
            return;
        }

        $this->clearAllCaches();
    }

    public function deleted(object $model): void
    {
        if ($model instanceof User) {
            return;
        }

        $this->clearAllCaches();
    }

    public function synced($model, string $relation): void
    {
        if ($model instanceof Role && $relation === 'menus') {
            $this->clearRoleUserCaches($model);
        }

        if ($model instanceof User && $relation === 'roles') {
            $this->clearUserCache($model);
        }

        if ($model instanceof Menu && $relation === 'roles') {
            $this->clearAllCaches();
        }
    }

    public function attached($model, string $relation): void
    {
        $this->synced($model, $relation);
    }

    public function detached($model, string $relation): void
    {
        $this->synced($model, $relation);
    }

    protected function clearAllCaches(): void
    {
        app(MenuPermissionManager::class)->clearAllCache();
        app(MenuCascadeService::class)->clearCache();
    }

    protected function clearUserCache(User $user): void
    {
        app(MenuPermissionManager::class)->clearUserCache($user);
    }

    protected function clearRoleUserCaches(Role $role): void
    {
        $manager = app(MenuPermissionManager::class);

        $role->users->each(function (User $user) use ($manager) {
            $manager->clearUserCache($user);
        });
    }
}