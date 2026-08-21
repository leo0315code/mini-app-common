<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Feedback;
use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 配置 API 限流器
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 关键模型变更自动记录审计日志
        User::observe(AuditObserver::class);
        Announcement::observe(AuditObserver::class);
        Feedback::observe(AuditObserver::class);
    }
}
