<?php

namespace App\Providers;

use App\Listeners\AdminAuthAuditListener;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * 关闭自动目录扫描（glob 在部分环境下会收到数组路径导致 FatalError），
     * 监听器统一显式登记在 listens() 中。
     */
    protected static $shouldDiscoverEvents = false;

    protected $listen = [
        Login::class => [
            AdminAuthAuditListener::class.'@handleLogin',
        ],
        Logout::class => [
            AdminAuthAuditListener::class.'@handleLogout',
        ],
        Failed::class => [
            AdminAuthAuditListener::class.'@handleFailed',
        ],
    ];
}
