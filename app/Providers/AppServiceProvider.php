<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\Notification;
use App\Models\User;
use App\Models\Role;
use App\Models\Menu;
use App\Policies\AnnouncementPolicy;
use App\Policies\ArticlePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\FeedbackPolicy;
use App\Policies\MenuPolicy;
use App\Policies\MediaPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Observers\AuditObserver;
use App\Observers\PermissionCacheObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        User::observe(AuditObserver::class);
        Announcement::observe(AuditObserver::class);
        Article::observe(AuditObserver::class);
        Category::observe(AuditObserver::class);
        Feedback::observe(AuditObserver::class);
        Role::observe(AuditObserver::class);
        Menu::observe(AuditObserver::class);

        Role::observe(PermissionCacheObserver::class);
        Menu::observe(PermissionCacheObserver::class);
        User::observe(PermissionCacheObserver::class);

        $this->registerPolicies();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Menu::class, MenuPolicy::class);
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Feedback::class, FeedbackPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
    }
}
