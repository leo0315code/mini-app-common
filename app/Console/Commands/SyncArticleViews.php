<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * 把 Redis 里的文章浏览计数器累加到数据库（articles:sync-views）。
 *
 * 设计：
 * - 详情接口只做 Redis 原子自增（无行锁热点），本命令定时批量落库。
 * - 每篇文章独立 key（article_views:{id}），扫描前缀批量取回。
 * - 落库用单条 UPDATE 原子加（views = views + counter），避免并发命令重复累加；
 *   落库成功后再删 key，防进程崩溃导致计数丢失/重复。
 * - 幂等：重复执行只累加一次（key 已删则跳过）。
 */
class SyncArticleViews extends Command
{
    protected $signature = 'articles:sync-views';

    protected $description = '把 Redis 文章浏览计数器累加到数据库并清零';

    public function handle(): int
    {
        // 用 KEYS 获取全部计数器 key。注意：Laravel Redis facade 的 keys()
        // 返回带 laravel-database- 前缀的完整 key（get/set 时框架自动处理前缀，
        // 但 keys 不处理），故用 str_contains 匹配、strrchr 提取 id。
        $keys = array_values(array_filter(
            Redis::keys(Article::VIEWS_COUNTER_PREFIX.'*'),
            fn ($key) => str_contains((string) $key, Article::VIEWS_COUNTER_PREFIX),
        ));

        if (empty($keys)) {
            $this->info('没有待同步的文章浏览计数。');

            return self::SUCCESS;
        }

        $total = 0;
        $synced = 0;

        DB::transaction(function () use ($keys, &$total, &$synced): void {
            foreach ($keys as $key) {
                // keys() 返回带 laravel-database- 前缀的完整 key；get/del 需用短 key
                // （框架自动加前缀，传完整 key 会双前缀读不到）
                $prefix = (string) (config('database.redis.options.prefix') ?? '');
                $shortKey = $prefix ? (string) substr((string) $key, strlen($prefix)) : (string) $key;
                $articleId = (int) substr((string) strrchr($shortKey, ':'), 1);
                $counter = (int) Redis::get($shortKey);

                if ($counter <= 0) {
                    Redis::del($shortKey);

                    continue;
                }

                $updated = DB::table('articles')
                    ->where('id', $articleId)
                    ->whereNull('deleted_at')
                    ->increment('views', $counter);

                // 落库成功累加计数；文章不存在/已软删时同样清理 key（防无限膨胀）
                Redis::del($shortKey);

                if ($updated) {
                    $total += $counter;
                    $synced++;
                }
            }
        });

        $this->info(sprintf('同步完成：%d 篇文章，累计 %d 次浏览。', $synced, $total));

        return self::SUCCESS;
    }
}
