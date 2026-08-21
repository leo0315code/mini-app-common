<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 媒体（文件）表：记录上传的图片/文件元信息，存于本地 public disk。
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('collection')->default('default')->comment('分组：default/avatar/announcement/...');
            $table->string('file_name')->comment('原始文件名');
            $table->string('path')->comment('磁盘相对路径');
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->string('url')->comment('可访问 URL');
            $table->unsignedBigInteger('size')->default(0)->comment('字节大小');
            $table->json('meta')->nullable()->comment('宽高/扩展信息等');
            $table->timestamps();

            $table->index(['collection', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
