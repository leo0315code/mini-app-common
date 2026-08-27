<?php

namespace App\Console\Commands;

use App\Services\ArticleViewCounter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 把 Redis 里的文章浏览计数器累加到数据库（articles:sync-views）。
 *
 * 设计：
 * - 详情接口只做 Redis 原子自增（无行锁热点），本命令定时批量落库。
 * - 每篇文章独立 key（article_views:{id}），扫描前缀批量取回。
 * - 落库用单条 UPDATE 原子加（views = views + counter），避免并发命令重复累加；
 *   落库成功后再删 key，防进程崩溃导致计数丢失/重复。
 * - 幂等：重复执行只累加一次（key 已删则跳过）。
 * - 计数器访问统一走 ArticleViewCounter（可注入，测试用内存实现）。
 */
class SyncArticleViews extends Command
{
    protected $signature = 'articles:sync-views';

    protected $description = '把 Redis 文章浏览计数器累加到数据库并清零';

    public function handle(ArticleViewCounter $counter): int
    {
        $pending = $counter->pendingCounters();

        if (empty($pending)) {
            $this->info('没有待同步的文章浏览计数。');

            return self::SUCCESS;
        }

        $total = 0;
        $synced = 0;

        DB::transaction(function () use ($pending, $counter, &$total, &$synced): void {
            foreach ($pending as $articleId => $count) {
                $updated = DB::table('articles')
                    ->where('id', $articleId)
                    ->whereNull('deleted_at')
                    ->increment('views', $count);

                // 落库成功累加计数；文章不存在/已软删时同样清理 key（防无限膨胀）
                $counter->clear($articleId);

                if ($updated) {
                    $total += $count;
                    $synced++;
                }
            }
        });

        $this->info(sprintf('同步完成：%d 篇文章，累计 %d 次浏览。', $synced, $total));

        return self::SUCCESS;
    }
}
