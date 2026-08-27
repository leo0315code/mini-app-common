<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 公开分类接口仅返回启用的分类。
     */
    public function test_active_categories_listed_publicly(): void
    {
        Category::factory()->count(2)->create(['is_active' => true]);
        Category::factory()->inactive()->create();

        $this->getJson('/api/article-categories')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(2, 'data');
    }

    /**
     * 已发布文章出现在公开列表，草稿不出现。
     */
    public function test_published_articles_listed_publicly(): void
    {
        Article::factory()->count(3)->create(['status' => Article::STATUS_PUBLISHED]);
        Article::factory()->draft()->create();

        $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(3, 'data');
    }

    /**
     * 可按分类筛选。
     */
    public function test_articles_filter_by_category(): void
    {
        $category = Category::factory()->create();
        Article::factory()->withCategory()->create(['category_id' => $category->id]);
        Article::factory()->create(['category_id' => null]);

        $this->getJson('/api/articles?category_id=' . $category->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * 已发布文章详情可访问；浏览数走 Redis 计数器（DB 不立即自增，
     * 由 articles:sync-views 定时落库）。
     */
    public function test_published_article_detail_increments_views(): void
    {
        $article = Article::factory()->create([
            'status' => Article::STATUS_PUBLISHED,
            'views' => 5,
        ]);

        // 清理可能残留的 Redis 计数器，保证断言确定性
        \Illuminate\Support\Facades\Redis::del(Article::VIEWS_COUNTER_PREFIX.$article->id);

        $this->getJson('/api/articles/' . $article->id)
            ->assertOk()
            ->assertJsonPath('data.id', $article->id);

        // DB 保持不变（未同步）
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'views' => 5,
        ]);

        // Redis 计数器 +1
        $this->assertSame(1, (int) \Illuminate\Support\Facades\Redis::get(
            Article::VIEWS_COUNTER_PREFIX.$article->id,
        ));
    }

    /**
     * 草稿文章详情接口返回 404。
     */
    public function test_draft_article_detail_hidden(): void
    {
        $draft = Article::factory()->draft()->create();

        $this->getJson('/api/articles/' . $draft->id)
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);
    }

    /**
     * 后台可访问分类列表页（管理员）。
     */
    public function test_admin_can_view_categories_list(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/console/categories')
            ->assertOk();
    }

    /**
     * 后台可访问文章列表页（管理员）。
     */
    public function test_admin_can_view_articles_list(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/console/articles')
            ->assertOk();
    }

    /**
     * 后台文章编辑弹窗可渲染（form 使用 Schemas\Components\Section，弹窗化后无独立 edit 路由）。
     */
    public function test_admin_can_edit_article(): void
    {
        $admin = $this->admin();
        $articles = Article::factory()->count(3)->create();
        $target = $articles->last();

        $this->actingAs($admin);

        Livewire::test(ListArticles::class)
            ->assertTableActionExists('edit');

        Livewire::test(ListArticles::class)->mountTableAction('edit', $target->id);
    }

    /**
     * 后台分类编辑弹窗可渲染（同样使用 Schemas\Components\Section）。
     */
    public function test_admin_can_edit_category(): void
    {
        $admin = $this->admin();
        $categories = Category::factory()->count(2)->create();
        $target = $categories->last();

        $this->actingAs($admin);

        Livewire::test(ListCategories::class)
            ->assertTableActionExists('edit');

        Livewire::test(ListCategories::class)->mountTableAction('edit', $target->id);
    }

    /**
     * 创建文章会自动写入审计日志（Observer 触发）。
     */
    public function test_article_creation_logged(): void
    {
        Category::factory()->create();

        Article::factory()->create([
            'title' => '测试文章',
            'content' => '正文内容',
            'status' => Article::STATUS_PUBLISHED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'article',
            'type' => 'create',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'email' => 'cms_admin@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole('super-admin');

        return $user;
    }
}
