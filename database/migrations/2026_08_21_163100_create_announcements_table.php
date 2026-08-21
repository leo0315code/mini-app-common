<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('标题');
            $table->longText('content')->comment('正文');
            $table->string('type')->default('notice')->comment('类型：notice 通知 / activity 活动 / update 版本更新');
            $table->string('status')->default('draft')->comment('状态：draft 草稿 / published 已发布 / offline 已下线');
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
