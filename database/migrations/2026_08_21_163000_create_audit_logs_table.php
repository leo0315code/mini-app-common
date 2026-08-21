<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->comment('操作类型：create/update/delete/login/config 等');
            $table->string('module')->comment('模块：user/token/announcement/feedback/system 等');
            $table->string('action')->nullable()->comment('具体操作描述');
            $table->text('description')->nullable()->comment('人类可读描述');
            $table->json('old_data')->nullable()->comment('变更前数据');
            $table->json('new_data')->nullable()->comment('变更后数据');
            $table->nullableMorphs('subject');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('url')->nullable()->comment('请求地址');
            $table->string('ip')->nullable()->comment('操作 IP');
            $table->timestamps();

            $table->index(['type', 'module']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
