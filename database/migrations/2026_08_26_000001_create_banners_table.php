<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120)->comment('运营位标题，便于后台辨识');
            $table->string('image')->comment('图片相对路径（public 磁盘）');
            $table->string('link_type', 20)->default('none')->comment('跳转类型 none|article|url');
            $table->unsignedBigInteger('article_id')->nullable()->comment('关联文章 ID（link_type=article 时有效）');
            $table->string('url', 500)->nullable()->comment('外部/页面链接（link_type=url 时有效）');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序，越小越靠前');
            $table->timestamp('starts_at')->nullable()->comment('生效开始时间，空表示立即生效');
            $table->timestamp('ends_at')->nullable()->comment('生效结束时间，空表示长期有效');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
