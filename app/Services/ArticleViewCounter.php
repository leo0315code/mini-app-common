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
        // 用 SCAN 游标迭代替代 KEYS：KEYS 在数据量大时会阻塞 Redis 单线程；
        // SCAN 增量遍历，对生产实例无阻塞风险。
        $prefix = (string) (config('database.redis.options.prefix') ?? '');
        $matchPrefix = $prefix . Article::VIEWS_COUNTER_PREFIX;
        $counters = [];

        $cursor = 0;
        do {
            // Laravel PhpRedisConnection::scan 返回 [newCursor, [keys]]，游标归零且无结果时为 false
            $result = Redis::scan($cursor, 'MATCH', $matchPrefix . '*', 'COUNT', 100);

            if ($result === false) {
                break;
            }

            [$cursor, $keys] = $result;

            foreach ($keys as $key) {
                $shortKey = $prefix ? (string) substr((string) $key, strlen($prefix)) : (string) $key;
                if (! str_contains($shortKey, Article::VIEWS_COUNTER_PREFIX)) {
                    continue;
                }

                $articleId = (int) substr((string) strrchr($shortKey, ':'), 1);
                $count = (int) Redis::get($shortKey);

                if ($count > 0) {
                    $counters[$articleId] = $count;
                }
            }
        } while ($cursor !== 0);

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
