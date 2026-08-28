<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 高频查询补充索引：
 *  - audit_logs.user_id：后台「用户审计 tab」按用户过滤/计数（UserResource）、AuditLogResource 按用户筛选。
 *  - articles.category_id：ArticleResource 列表 SelectFilter 按分类过滤。
 *  - notifications.published：NotificationController 列表/unread、NotificationResource 过滤、Jobs 多次 where('published', true)。
 *    与 notification_user(user_id, read) 组合索引分开，单列 published 覆盖「仅按发布态过滤」的场景。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id', 'audit_logs_user_id_index');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->index('category_id', 'articles_category_id_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index('published', 'notifications_published_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_user_id_index');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_category_id_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_published_index');
        });
    }
};
