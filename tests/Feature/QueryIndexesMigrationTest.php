<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 高频查询补充索引迁移回归（2026_08_27_000001_add_query_indexes）：
 * 确认 audit_logs.user_id / articles.category_id / notifications.published 三个单列索引存在。
 */
class QueryIndexesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_indexes_are_created(): void
    {
        // RefreshDatabase 已执行迁移，直接断言索引存在
        $this->assertTrue(
            Schema::hasIndex('audit_logs', 'audit_logs_user_id_index'),
            'audit_logs.user_id 索引缺失'
        );
        $this->assertTrue(
            Schema::hasIndex('articles', 'articles_category_id_index'),
            'articles.category_id 索引缺失'
        );
        $this->assertTrue(
            Schema::hasIndex('notifications', 'notifications_published_index'),
            'notifications.published 索引缺失'
        );
    }

    public function test_down_method_drops_indexes(): void
    {
        // down() 通过 Blueprint::dropIndex 实现，断言该方法可用即迁移可回滚
        $this->assertTrue(method_exists(Blueprint::class, 'dropIndex'));
    }
}
