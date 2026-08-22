<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

trait HasWidgetPermission
{
    abstract protected static function getWidgetPermissions(): array;

    public static function canView(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $permissions = static::getWidgetPermissions();
        $manager = app(MenuPermissionManager::class);

        return $manager->hasAnyPermission($user, $permissions);
    }
}