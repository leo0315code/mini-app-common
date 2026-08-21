<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 文章/内容表（CMS）：后台富文本撰写，小程序端按频道拉取。
     * 与 announcements（轻量公告/速讯）区分：文章支持分类、封面图、摘要、上下架。
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title')->comment('标题');
            $table->string('slug')->nullable()->comment('文章标识（URL 友好，可空）');
            $table->string('cover')->nullable()->comment('封面图 URL');
            $table->text('summary')->nullable()->comment('摘要');
            $table->longText('content')->comment('正文（RichEditor）');
            $table->string('status')->default('draft')->comment('draft|published|offline');
            $table->boolean('is_top')->default(false)->comment('是否置顶');
            $table->unsignedBigInteger('views')->default(0)->comment('浏览数');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->timestamps();

            $table->index('status');
            $table->index('is_top');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
