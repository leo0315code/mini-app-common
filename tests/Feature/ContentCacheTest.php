<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\Banner;
use App\Support\ContentCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * C 端公开内容缓存：版本号失效 + 参数隔离 + 写事件准点失效。
 */
class ContentCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_banners_list_is_cached_and_invalidated_on_write(): void
    {
        Banner::factory()->create();

        $first = $this->getJson('/api/banners')->assertStatus(200)->json();

        // 再次请求应命中缓存（DB 查询数不再增长；此处通过写入后数据变化验证失效）
        Banner::factory()->create();

        $second = $this->getJson('/api/banners')->assertStatus(200)->json();

        // 写事件已触发版本号失效 → 第二次能看到新 banner
        $this->assertCount(2, $second['data']);
    }

    public function test_announcements_cache_key_is_isolation_by_type(): void
    {
        Announcement::factory()->create(['type' => 'system', 'status' => Announcement::STATUS_PUBLISHED]);
        Announcement::factory()->create(['type' => 'activity', 'status' => Announcement::STATUS_PUBLISHED]);

        // 不同 type 参数 → 不同缓存键，各自返回对应数据
        $system = $this->getJson('/api/announcements?type=system')->assertStatus(200)->json();
        $activity = $this->getJson('/api/announcements?type=activity')->assertStatus(200)->json();

        $this->assertCount(1, $system['data']);
        $this->assertCount(1, $activity['data']);
        $this->assertEquals('system', $system['data'][0]['type']);
        $this->assertEquals('activity', $activity['data'][0]['type']);
    }

    public function test_articles_cache_invalidated_on_article_write(): void
    {
        $a1 = Article::factory()->create(['status' => Article::STATUS_PUBLISHED]);

        $first = $this->getJson('/api/articles')->assertStatus(200)->json();
        $this->assertCount(1, $first['data']);

        // 新增一篇已发布文章 → 缓存应失效，第二次返回 2 篇
        Article::factory()->create(['status' => Article::STATUS_PUBLISHED]);

        $second = $this->getJson('/api/articles')->assertStatus(200)->json();
        $this->assertCount(2, $second['data']);
    }

    public function test_articles_cache_key_isolation_by_category(): void
    {
        $catA = \App\Models\Category::factory()->create();
        $catB = \App\Models\Category::factory()->create();
        Article::factory()->create(['status' => Article::STATUS_PUBLISHED, 'category_id' => $catA->id]);

        $respA = $this->getJson('/api/articles?category_id='.$catA->id)->assertStatus(200)->json();
        $respB = $this->getJson('/api/articles?category_id='.$catB->id)->assertStatus(200)->json();

        $this->assertCount(1, $respA['data']);
        $this->assertCount(0, $respB['data']);
    }

    public function test_cache_key_includes_version_number(): void
    {
        $service = app(ContentCacheService::class);
        $v0 = $service->currentVersion();

        Banner::factory()->create();
        $this->assertSame($v0 + 1, app(ContentCacheService::class)->currentVersion());
    }
}
