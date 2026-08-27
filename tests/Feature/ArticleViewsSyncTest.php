<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * 文章浏览数：Redis 原子自增 + articles:sync-views 定时落库。
 */
class ArticleViewsSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushdb(); // 隔离测试环境 Redis，保证确定性
    }

    public function test_detail_visit_increments_redis_counter_not_db(): void
    {
        $article = Article::factory()->create(['status' => Article::STATUS_PUBLISHED, 'views' => 0]);

        $this->getJson('/api/articles/'.$article->id)->assertStatus(200);

        // DB 未直接自增
        $this->assertSame(0, $article->fresh()->views);

        // Redis 计数器 +1
        $this->assertSame(1, (int) Redis::get(Article::VIEWS_COUNTER_PREFIX.$article->id));
    }

    public function test_sync_command_flushes_counter_into_db(): void
    {
        $article = Article::factory()->create(['status' => Article::STATUS_PUBLISHED, 'views' => 5]);

        // 模拟 3 次访问
        Redis::incr(Article::VIEWS_COUNTER_PREFIX.$article->id);
        Redis::incr(Article::VIEWS_COUNTER_PREFIX.$article->id);
        Redis::incr(Article::VIEWS_COUNTER_PREFIX.$article->id);

        $this->artisan('articles:sync-views')
            ->expectsOutputToContain('同步完成')
            ->assertExitCode(0);

        // 落库：5 + 3 = 8
        $this->assertSame(8, $article->fresh()->views);

        // 计数器已清零（Redis::exists 返回 0/1 整型）
        $this->assertSame(0, Redis::exists(Article::VIEWS_COUNTER_PREFIX.$article->id));
    }

    public function test_sync_is_idempotent(): void
    {
        $article = Article::factory()->create(['status' => Article::STATUS_PUBLISHED, 'views' => 0]);

        Redis::incr(Article::VIEWS_COUNTER_PREFIX.$article->id);

        // 执行两次：只累加一次
        $this->artisan('articles:sync-views')->assertExitCode(0);
        $this->artisan('articles:sync-views')->assertExitCode(0);

        $this->assertSame(1, $article->fresh()->views);
    }

    public function test_sync_skips_soft_deleted_articles(): void
    {
        $article = Article::factory()->create(['status' => Article::STATUS_PUBLISHED, 'views' => 0]);
        $article->delete(); // 软删除

        Redis::incr(Article::VIEWS_COUNTER_PREFIX.$article->id);

        $this->artisan('articles:sync-views')->assertExitCode(0);

        // 软删文章不累加，但计数器被清理（避免无限膨胀）
        $this->assertSame(0, $article->fresh()->views);
        $this->assertSame(0, Redis::exists(Article::VIEWS_COUNTER_PREFIX.$article->id));
    }
}
