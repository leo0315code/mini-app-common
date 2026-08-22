<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    protected string $permissionPrefix = '';

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('roles')
            || ! \Illuminate\Support\Facades\Schema::hasTable('menus')
            || ! \Illuminate\Support\Facades\Schema::hasTable('menu_role')) {
            return null;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAnyPermission($user, [
            "{$this->permissionPrefix}.view",
            "{$this->permissionPrefix}.manage",
        ]);
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->hasAnyPermission($user, [
            "{$this->permissionPrefix}.view",
            "{$this->permissionPrefix}.manage",
        ]);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, "{$this->permissionPrefix}.manage");
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->hasPermission($user, "{$this->permissionPrefix}.manage");
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->hasPermission($user, "{$this->permissionPrefix}.manage");
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasPermission($user, "{$this->permissionPrefix}.manage");
    }

    protected function hasAnyPermission(User $user, array $permissions): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $hasTable = \Illuminate\Support\Facades\Schema::hasTable('roles')
            && \Illuminate\Support\Facades\Schema::hasTable('menus')
            && \Illuminate\Support\Facades\Schema::hasTable('menu_role');

        if (! $hasTable) {
            return true;
        }

        // 用户无任何角色时，视为未初始化的系统，放行以兼容旧逻辑
        if ($user->roles()->count() === 0) {
            return true;
        }

        return $user->roles()
            ->whereHas('menus', function ($query) use ($permissions) {
                $query->whereIn('permission', $permissions);
            })
            ->exists();
    }

    protected function hasPermission(User $user, string $permission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $hasTable = \Illuminate\Support\Facades\Schema::hasTable('roles')
            && \Illuminate\Support\Facades\Schema::hasTable('menus')
            && \Illuminate\Support\Facades\Schema::hasTable('menu_role');

        if (! $hasTable) {
            return true;
        }

        // 用户无任何角色时，视为未初始化的系统，放行以兼容旧逻辑
        if ($user->roles()->count() === 0) {
            return true;
        }

        return $user->roles()
            ->whereHas('menus', function ($query) use ($permission) {
                $query->where('permission', $permission)
                    ->orWhere('permission', 'like', str_replace('.', '.%', $permission));
            })
            ->exists();
    }
}
