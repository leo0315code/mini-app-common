<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Redis;

/**
 * 文章浏览数计数器（Redis 原子自增，articles:sync-views 定时落库）。
 *
 * 设计为可注入服务：生产用 Redis（原子无锁），测试可替换为内存实现，
 * 避免测试依赖真实 Redis 服务（CI 无 Redis 也能全绿）。
 */
class ArticleViewCounter
{
    /**
     * 记录一次浏览（原子自增）。
     */
    public function increment(int $articleId): void
    {
        Redis::incr($this->key($articleId));
    }

    /**
     * 返回全部待落库计数：[articleId => count]。
     */
    public function pendingCounters(): array
    {
        // keys() 返回带 laravel-database- 前缀的完整 key；get 需短 key
        $prefix = (string) (config('database.redis.options.prefix') ?? '');
        $counters = [];

        foreach (Redis::keys(Article::VIEWS_COUNTER_PREFIX.'*') as $key) {
            if (! str_contains((string) $key, Article::VIEWS_COUNTER_PREFIX)) {
                continue;
            }

            $shortKey = $prefix ? (string) substr((string) $key, strlen($prefix)) : (string) $key;
            $articleId = (int) substr((string) strrchr($shortKey, ':'), 1);
            $count = (int) Redis::get($shortKey);

            if ($count > 0) {
                $counters[$articleId] = $count;
            }
        }

        return $counters;
    }

    /**
     * 清理某篇文章的计数器（落库成功后调用）。
     */
    public function clear(int $articleId): void
    {
        Redis::del($this->key($articleId));
    }

    protected function key(int $articleId): string
    {
        return Article::VIEWS_COUNTER_PREFIX.$articleId;
    }
}
