<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * C 端公开内容缓存（公告/文章/运营位列表）。
 *
 * 失效策略（双保险，兼容不支持 tags 的缓存驱动）：
 * 1. 版本号：内容模型任一写事件 → 版本号 +1 → 旧键全部失效（天然准点）。
 * 2. 短 TTL 兜底：`published_at` 定时发布的文章，即使无人触发写事件，
 *    也最多延迟一个 TTL（默认 120s）后可见；`publish:scheduled` 命令执行时
 *    显式 bumpVersion() 做到准点。
 *
 * 用法：Cache::remember 的闭包外包一层本服务的 remember()，
 * 键自动带版本号，无需手工拼版本前缀。
 */
class ContentCacheService
{
    public const CACHE_PREFIX = 'content_cache';

    public const DEFAULT_TTL = 120; // 秒

    public function __construct(
        protected int $ttl = self::DEFAULT_TTL,
    ) {}

    /**
     * 带版本号的 remember：key 追加当前版本号，内容变更后自动失效。
     */
    public function remember(string $key, callable $callback): mixed
    {
        return Cache::remember(
            $this->cacheKey($key),
            $this->ttl,
            $callback,
        );
    }

    /**
     * 内容变更时调用：版本号 +1，全部内容缓存失效。
     */
    public function bumpVersion(): void
    {
        Cache::forever($this->versionCacheKey(), $this->currentVersion() + 1);
    }

    public function currentVersion(): int
    {
        return (int) Cache::get($this->versionCacheKey(), 0);
    }

    protected function cacheKey(string $key): string
    {
        return self::CACHE_PREFIX.'_'.$this->currentVersion().'_'.$key;
    }

    protected function versionCacheKey(): string
    {
        return self::CACHE_PREFIX.'_version';
    }
}
