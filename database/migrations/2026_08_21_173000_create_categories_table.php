<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 内容分类表（CMS）：文章/内容频道的栏目。
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('分类名称');
            $table->string('slug')->unique()->comment('分类标识（URL 友好）');
            $table->string('description')->nullable()->comment('分类说明');
            $table->unsignedBigInteger('sort')->default(0)->comment('排序，越小越靠前');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
