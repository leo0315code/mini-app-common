<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P3-10 软删除回收站：内容模型文章/公告/媒体支持软删与恢复，
 * 公开接口与后台默认列表均排除回收站内容，硬删除才真正清记录/文件。
 */
class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_soft_delete_excluded_from_public_api(): void
    {
        $live = Article::factory()->create(['status' => Article::STATUS_PUBLISHED]);
        $trashed = Article::factory()->create(['status' => Article::STATUS_PUBLISHED]);
        $trashed->delete();

        $this->assertSoftDeleted('articles', ['id' => $trashed->id]);

        $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $live->id);

        // 软删文章详情对小程序不可见
        $this->getJson('/api/articles/' . $trashed->id)
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);
    }

    public function test_announcement_soft_delete_excluded_from_public_api(): void
    {
        $live = Announcement::factory()->create(['status' => Announcement::STATUS_PUBLISHED]);
        $trashed = Announcement::factory()->create(['status' => Announcement::STATUS_PUBLISHED]);
        $trashed->delete();

        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $live->id);
    }

    public function test_article_can_be_restored(): void
    {
        $article = Article::factory()->create();
        $article->delete();

        $this->assertSoftDeleted('articles', ['id' => $article->id]);

        $article->restore();

        $this->assertNotSoftDeleted('articles', ['id' => $article->id]);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
    }

    public function test_force_delete_removes_record(): void
    {
        $article = Article::factory()->create();
        $article->forceDelete();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    public function test_media_soft_delete_keeps_file_but_force_delete_removes_it(): void
    {
        Storage::fake('public');

        $media = Media::factory()->create([
            'disk' => 'public',
            'path' => 'uploads/test.txt',
            'url' => '',
        ]);
        // 模拟磁盘文件存在
        Storage::disk('public')->put($media->path, 'hello');
        $this->assertTrue(Storage::disk('public')->exists($media->path));

        // 软删除：保留文件
        $media->delete();
        $this->assertSoftDeleted('media', ['id' => $media->id]);
        $this->assertTrue(Storage::disk('public')->exists($media->path), '软删除不应删除磁盘文件');

        // 硬删除：清文件
        $media->forceDelete();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        $this->assertFalse(Storage::disk('public')->exists($media->path), '硬删除应清理磁盘文件');
    }

    public function test_only_trashed_query_returns_soft_deleted_only(): void
    {
        $live = Article::factory()->create();
        $gone = Article::factory()->create();
        $gone->delete();

        $trashed = Article::query()->onlyTrashed()->get();
        $this->assertCount(1, $trashed);
        $this->assertEquals($gone->id, $trashed->first()->id);

        // 默认查询（不带 withTrashed）仅返回未删除记录
        $visible = Article::query()->get();
        $this->assertCount(1, $visible);
        $this->assertEquals($live->id, $visible->first()->id);

        // 包含软删后才是两条
        $all = Article::query()->withTrashed()->get();
        $this->assertCount(2, $all);
    }
}
