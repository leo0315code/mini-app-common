<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Request;

/**
 * 监听后台登录相关事件，写入操作审计日志。
 * 覆盖 Eloquent 观察者未触及的登录/登出/失败场景。
 */
class AdminAuthAuditListener
{
    public function handleLogin(Login $event): void
    {
        // 仅记录后台面板（filament guard）的登录
        if ($event->guard !== 'filament' && $event->guard !== 'web') {
            return;
        }

        /** @var User|null $user */
        $user = $event->user;

        Audit::log(
            type: 'login',
            module: 'auth',
            action: '后台登录成功',
            user: $user,
        );
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->guard !== 'filament' && $event->guard !== 'web') {
            return;
        }

        /** @var User|null $user */
        $user = $event->user;

        Audit::log(
            type: 'logout',
            module: 'auth',
            action: '后台退出登录',
            user: $user,
        );
    }

    public function handleFailed(Failed $event): void
    {
        if ($event->guard !== 'filament' && $event->guard !== 'web') {
            return;
        }

        $credentials = $event->credentials ?? [];

        Audit::log(
            type: 'login_failed',
            module: 'auth',
            action: '后台登录失败：账号 '.($credentials['email'] ?? '未知'),
            user: null,
        );
    }
}
