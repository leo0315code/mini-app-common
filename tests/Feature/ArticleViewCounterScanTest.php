<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Services\ArticleViewCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * ArticleViewCounter::pendingCounters 优化回归：
 *  - KEYS 改为 SCAN 游标迭代（避免阻塞 Redis 单线程）
 *  - prefix 剥离、shortKey 解析、>0 过滤逻辑保持不变
 *
 * 测试环境无真实 Redis，故用轻量fake替换 Redis 门面，模拟两段式 SCAN 游标返回。
 */
class ArticleViewCounterScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_counters_iterates_via_scan_with_prefix_strip(): void
    {
        config(['database.redis.options.prefix' => 'laravel-database-']);

        $prefix = 'laravel-database-';
        $matchPrefix = $prefix.Article::VIEWS_COUNTER_PREFIX;

        // 模拟 SCAN：第一段返回 cursor=5 + 一个 key；第二段返回 cursor=0（结束）+ 一个 key
        $fake = new class($prefix, $matchPrefix)
        {
            private int $step = 0;

            public function __construct(private string $p, private string $m) {}

            public function scan(...$args): array
            {
                $this->step++;

                return match ($this->step) {
                    1 => [5, [$this->p.Article::VIEWS_COUNTER_PREFIX.'10']],
                    default => [0, [$this->p.Article::VIEWS_COUNTER_PREFIX.'20']],
                };
            }

            public function get(string $key): string
            {
                // 传入的是已剥离 prefix 的 shortKey
                return match ($key) {
                    Article::VIEWS_COUNTER_PREFIX.'10' => '3',
                    Article::VIEWS_COUNTER_PREFIX.'20' => '5',
                    default => '0',
                };
            }
        };

        $original = Redis::getFacadeRoot();
        Redis::swap($fake);

        try {
            $counters = app(ArticleViewCounter::class)->pendingCounters();
        } finally {
            Redis::swap($original);
        }

        // 10 => 3, 20 => 5
        $this->assertSame([10 => 3, 20 => 5], $counters);
    }

    public function test_pending_counters_skips_zero_and_unrelated_keys(): void
    {
        config(['database.redis.options.prefix' => 'laravel-database-']);
        $prefix = 'laravel-database-';

        $fake = new class($prefix)
        {
            public function __construct(private string $p) {}

            public function scan(...$args): array
            {
                // 一次返回完毕：含一个无关 key + 一个计数=0 的 key + 一个有效 key
                return [0, [
                    $this->p.'session:abc',                       // 不匹配 VIEWS_COUNTER_PREFIX
                    $this->p.Article::VIEWS_COUNTER_PREFIX.'5',   // 计数 0，应被丢弃
                    $this->p.Article::VIEWS_COUNTER_PREFIX.'7',   // 有效
                ]];
            }

            public function get(string $key): string
            {
                if ($key === Article::VIEWS_COUNTER_PREFIX.'5') {
                    return '0';
                }
                if ($key === Article::VIEWS_COUNTER_PREFIX.'7') {
                    return '9';
                }

                return '0';
            }
        };

        $original = Redis::getFacadeRoot();
        Redis::swap($fake);

        try {
            $counters = app(ArticleViewCounter::class)->pendingCounters();
        } finally {
            Redis::swap($original);
        }

        // 无关 key 与计数 0 的 key 均被忽略
        $this->assertSame([7 => 9], $counters);
    }
}
