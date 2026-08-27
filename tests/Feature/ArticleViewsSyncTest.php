<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Services\ArticleViewCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 文章浏览数：详情接口走计数器服务（Redis 原子自增），
 * articles:sync-views 定时落库。
 *
 * 测试用内存实现替换 ArticleViewCounter，不依赖真实 Redis
 * （CI 无 Redis 服务也能全绿）。
 */
class ArticleViewsSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 内存版计数器：模拟 Redis 行为，供测试注入。
     */
    private function fakeCounter(array $seed = []): ArticleViewCounter
    {
        $counts = $seed;

        $fake = new class($counts) extends ArticleViewCounter
        {
            public function __construct(public array $counts) {}

            public function increment(int $articleId): void
            {
                $this->counts[$articleId] = ($this->counts[$articleId] ?? 0) + 1;
            }

            public function pendingCounters(): array
            {
                return $this->counts;
            }

            public function clear(int $articleId): void
            {
                unset($this->counts[$articleId]);
            }
        };

        $this->app->instance(ArticleViewCounter::class, $fake);

        return $fake;
    }

    public function test_detail_visit_increments_counter_not_db(): void
    {
        $counter = $this->fakeCounter();
        $article = Article::factory()->create(['status' => Article::STATUS_PUBLISHED, 'views' => 0]);

        $this->getJson('/api/articles/'.$article->id)->assertStatus(200);

        // DB 未直接自增
        $this->assertSame(0, $article->fresh()->views);

        // 计数器 +1
        $this->assertSame(1, $counter->counts[$article->id] ?? 0);
    }

    public function test_sync_command_flushes_counter_into_db(): void
    {
        $article = Article::factory()->create(['status' => Article::STATUS_PUBLISHED, 'views' => 5]);

        // 模拟 3 次访问
        $counter = $this->fakeCounter([$article->id => 3]);

        $this->artisan('articles:sync-views')
            ->expectsOutputToContain('同步完成')
            ->assertExitCode(0);

        // 落库：5 + 3 = 8
        $this->assertSame(8, $article->fresh()->views);

        // 计数器已清零
        $this->assertArrayNotHasKey($article->id, $counter->counts);
    }

    public function test_sync_is_idempotent(): void
    {
        $article = Article::factory()->create(['status' => Article::STATUS_PUBLISHED, 'views' => 0]);

        $counter = $this->fakeCounter([$article->id => 1]);

        // 执行两次：第一次已清零，第二次无待同步
        $this->artisan('articles:sync-views')->assertExitCode(0);
        $this->artisan('articles:sync-views')->assertExitCode(0);

        $this->assertSame(1, $article->fresh()->views);
    }

    public function test_sync_skips_soft_deleted_articles(): void
    {
        $article = Article::factory()->create(['status' => Article::STATUS_PUBLISHED, 'views' => 0]);
        $article->delete(); // 软删除

        $counter = $this->fakeCounter([$article->id => 1]);

        $this->artisan('articles:sync-views')->assertExitCode(0);

        // 软删文章不累加，但计数器被清理（避免无限膨胀）
        $this->assertSame(0, $article->fresh()->views);
        $this->assertArrayNotHasKey($article->id, $counter->counts);
    }
}
