<?php

namespace App\Observers;

use App\Support\ContentCacheService;

/**
 * C 端公开内容缓存失效：公告/文章/分类/运营位任一写事件
 * （创建/更新/删除/恢复）→ 版本号 +1 → 全部内容缓存准点失效。
 */
class ContentCacheObserver
{
    public function __construct(
        protected ContentCacheService $cache,
    ) {}

    public function created(object $model): void
    {
        $this->cache->bumpVersion();
    }

    public function updated(object $model): void
    {
        $this->cache->bumpVersion();
    }

    public function deleted(object $model): void
    {
        $this->cache->bumpVersion();
    }

    public function restored(object $model): void
    {
        $this->cache->bumpVersion();
    }
}
