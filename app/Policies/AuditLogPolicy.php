<?php

namespace App\Policies;

class AuditLogPolicy extends BasePolicy
{
    protected string $permissionPrefix = 'audit-log';

    public function create($user): bool
    {
        return false;
    }

    public function update($user, $model): bool
    {
        return false;
    }

    public function delete($user, $model): bool
    {
        return false;
    }

    public function deleteAny($user): bool
    {
        return false;
    }
}
