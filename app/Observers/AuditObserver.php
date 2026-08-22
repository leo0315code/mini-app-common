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
        $attached = $changes['attached'] ?? [];
        $detached = $changes['detached'] ?? [];
        $updated = $changes['updated'] ?? [];

        $oldIds = $this->getOldIds($model, $relation, $attached, $detached);
        $newIds = $this->getNewIds($model, $relation);

        $oldData = [
            'ids' => $oldIds,
            'attached' => $attached,
            'detached' => $detached,
            'updated' => $updated,
        ];

        $newData = [
            'ids' => $newIds,
        ];

        if ($model instanceof Role && $relation === 'menus') {
            Audit::log(
                type: 'permission',
                module: 'role',
                action: '同步角色权限 #' . $model->getKey(),
                oldData: $oldData,
                newData: $newData,
                subject: $model,
            );
        }

        if ($model instanceof Menu && $relation === 'roles') {
            Audit::log(
                type: 'permission',
                module: 'menu',
                action: '同步菜单角色 #' . $model->getKey(),
                oldData: $oldData,
                newData: $newData,
                subject: $model,
            );
        }

        if ($model instanceof User && $relation === 'roles') {
            Audit::log(
                type: 'permission',
                module: 'user',
                action: '同步用户角色 #' . $model->getKey(),
                oldData: $oldData,
                newData: $newData,
                subject: $model,
            );
        }
    }

    protected function getOldIds($model, string $relation, array $attached, array $detached): array
    {
        $currentIds = $this->getNewIds($model, $relation);

        return array_values(array_unique(array_merge(
            array_diff($currentIds, $attached),
            $detached,
        )));
    }

    protected function getNewIds($model, string $relation): array
    {
        $query = $model->$relation();

        return $query->get()->pluck($query->getRelated()->getKeyName())->all();
    }

    public function attached($model, string $relation, array $pivotIds): void
    {
        $this->logPivotChange($model, $relation, $pivotIds, [], 'attach');
    }

    public function detached($model, string $relation, array $pivotIds): void
    {
        $this->logPivotChange($model, $relation, [], $pivotIds, 'detach');
    }

    protected function logPivotChange($model, string $relation, array $attached, array $detached, string $type): void
    {
        if (! $model instanceof Role && ! $model instanceof Menu && ! $model instanceof User) {
            return;
        }

        if ($relation !== 'menus' && $relation !== 'roles') {
            return;
        }

        $newIds = $this->getNewIds($model, $relation);
        $oldIds = array_values(array_unique(array_merge(
            array_diff($newIds, $attached),
            $detached,
        )));

        $oldData = ['ids' => $oldIds];
        $newData = ['ids' => $newIds];

        $module = match (true) {
            $model instanceof Role => 'role',
            $model instanceof Menu => 'menu',
            $model instanceof User => 'user',
            default => 'system',
        };

        $action = match ($type) {
            'attach' => '添加关联',
            'detach' => '移除关联',
            default => '同步关联',
        };

        Audit::log(
            type: 'permission',
            module: $module,
            action: $action . ' #' . $model->getKey(),
            oldData: $oldData,
            newData: $newData,
            subject: $model,
        );
    }
}
