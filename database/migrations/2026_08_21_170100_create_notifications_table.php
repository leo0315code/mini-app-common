<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 站内通知表 + 接收人已读回执表。
     * 通知为一对多「广播消息」模型：发送时按 scope 展开收件人，
     * 写入 notification_user 回执，记录已读状态。
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->comment('标题');
            $table->text('body')->comment('正文');
            $table->string('type')->default('system')->comment('类型：system/activity/version');
            $table->string('scope')->default('all')->comment('范围：all=全部用户/specified=指定用户/registered=已注册');
            $table->json('targets')->nullable()->comment('scope=specified 时的目标用户 id 列表');
            $table->boolean('published')->default(true)->comment('是否立即发布');
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->timestamps();
        });

        Schema::create('notification_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('read')->default(false)->comment('是否已读');
            $table->timestamp('read_at')->nullable()->comment('已读时间');
            $table->timestamps();

            $table->unique(['notification_id', 'user_id']);
            $table->index(['user_id', 'read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_user');
        Schema::dropIfExists('notifications');
    }
};
