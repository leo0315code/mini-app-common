<?php

namespace App\Policies;

use App\Models\User;
use App\Support\MenuPermissionManager;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Schema;

abstract class BasePolicy
{
    use HandlesAuthorization;

    protected string $permissionPrefix = '';

    protected bool $schemaChecked = false;

    protected bool $tablesReady = false;

    public function __construct(
        protected MenuPermissionManager $permissionManager,
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->schemaChecked) {
            $this->tablesReady = Schema::hasTable('roles')
                && Schema::hasTable('menus')
                && Schema::hasTable('menu_role');
            $this->schemaChecked = true;
        }

        if (! $this->tablesReady) {
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