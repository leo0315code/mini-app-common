<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 内容类模型（文章 / 公告 / 媒体）软删除支持，用于后台「回收站」。
     *
     * 软删除只标记 deleted_at，保留记录与文件，可恢复；
     * 硬删除（forceDelete）才真正清记录，文件清理交给应用层处理。
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
