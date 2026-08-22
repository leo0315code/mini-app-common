<?php

namespace App\Policies;

use App\Models\User;
use App\Support\MenuPermissionManager;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    protected string $permissionPrefix = '';

    public function __construct(
        protected MenuPermissionManager $permissionManager,
    ) {}

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

        if ($user->roles()->count() === 0) {
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
        return $this->permissionManager->hasAnyPermission($user, $permissions);
    }

    protected function hasPermission(User $user, string $permission): bool
    {
        return $this->permissionManager->hasPermission($user, $permission);
    }
}