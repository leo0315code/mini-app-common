<?php

namespace App\Policies;

class MenuPolicy extends BasePolicy
{
    protected string $permissionPrefix = 'menu';

    public function create($user): bool
    {
        return $this->hasPermission($user, 'menu.manage');
    }

    public function update($user, $model): bool
    {
        return $this->hasPermission($user, 'menu.manage');
    }

    public function delete($user, $model): bool
    {
        return $this->hasPermission($user, 'menu.manage');
    }

    public function deleteAny($user): bool
    {
        return $this->hasPermission($user, 'menu.manage');
    }
}
