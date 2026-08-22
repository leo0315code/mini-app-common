<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit;

/**
 * 通过 Eloquent 事件自动记录关键模型的新增 / 修改 / 删除审计日志。
 */
class AuditObserver
{
    protected static function diff($model): array
    {
        $dirty = $model->getDirty();
        $old = [];
        $new = [];

        foreach ($dirty as $key => $value) {
            if (in_array($key, ['updated_at'], true)) {
                continue;
            }
            $old[$key] = $model->getOriginal($key);
            $new[$key] = $value;
        }

        return ['old' => $old, 'new' => $new];
    }

    protected static function module($model): string
    {
        return match (get_class($model)) {
            Announcement::class => 'announcement',
            Article::class => 'article',
            Category::class => 'category',
            Feedback::class => 'feedback',
            User::class => 'user',
            Role::class => 'role',
            Menu::class => 'menu',
            default => 'system',
        };
    }

    protected static function isAuditable($model): bool
    {
        return $model instanceof Announcement
            || $model instanceof Article
            || $model instanceof Category
            || $model instanceof Feedback
            || $model instanceof User
            || $model instanceof Role
            || $model instanceof Menu;
    }

    public function created($model): void
    {
        if (! self::isAuditable($model)) {
            return;
        }

        $diff = self::diff($model);

        Audit::log(
            type: 'create',
            module: self::module($model),
            action: '新增' . class_basename($model) . ' #' . $model->getKey(),
            oldData: $diff['old'],
            newData: $model->getAttributes(),
            subject: $model,
        );
    }

    public function updated($model): void
    {
        if (! self::isAuditable($model)) {
            return;
        }

        $diff = self::diff($model);

        if (empty($diff['old'])) {
            return;
        }

        Audit::log(
            type: 'update',
            module: self::module($model),
            action: '修改' . class_basename($model) . ' #' . $model->getKey(),
            oldData: $diff['old'],
            newData: $diff['new'],
            subject: $model,
        );
    }

    public function deleted($model): void
    {
        if (! self::isAuditable($model)) {
            return;
        }

        Audit::log(
            type: 'delete',
            module: self::module($model),
            action: '删除' . class_basename($model) . ' #' . $model->getKey(),
            oldData: $model->getOriginal(),
            subject: $model,
        );
    }

    public function synced($model, string $relation, array $changes): void
    {
        if ($model instanceof Role && $relation === 'menus') {
            Audit::log(
                type: 'permission',
                module: 'role',
                action: '同步角色权限 #' . $model->getKey(),
                oldData: $changes['old'] ?? [],
                newData: $changes['new'] ?? [],
                subject: $model,
            );
        }

        if ($model instanceof Menu && $relation === 'roles') {
            Audit::log(
                type: 'permission',
                module: 'menu',
                action: '同步菜单角色 #' . $model->getKey(),
                oldData: $changes['old'] ?? [],
                newData: $changes['new'] ?? [],
                subject: $model,
            );
        }
    }
}
