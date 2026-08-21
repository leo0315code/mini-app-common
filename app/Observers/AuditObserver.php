<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\User;
use App\Services\Audit;

/**
 * 通过 Eloquent 事件自动记录关键模型的新增 / 修改 / 删除审计日志。
 */
class AuditObserver
{
    /**
     * 计算可审计的字段差异。
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
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
            default => 'system',
        };
    }

    public function created($model): void
    {
        if (! $model instanceof Announcement && ! $model instanceof Article
            && ! $model instanceof Category && ! $model instanceof Feedback
            && ! $model instanceof User) {
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
        if (! $model instanceof Announcement && ! $model instanceof Article
            && ! $model instanceof Category && ! $model instanceof Feedback
            && ! $model instanceof User) {
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
        if (! $model instanceof Announcement && ! $model instanceof Article
            && ! $model instanceof Category && ! $model instanceof Feedback
            && ! $model instanceof User) {
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
}
