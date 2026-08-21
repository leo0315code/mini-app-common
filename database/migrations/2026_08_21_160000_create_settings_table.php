<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 系统配置表：以 key/value 形式存储可后台维护的配置项。
     * value 为 JSON，可存放字符串、数字、数组等任意标量结构。
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general')->comment('配置分组');
            $table->string('key')->comment('配置键');
            $table->json('value')->nullable()->comment('配置值（JSON）');
            $table->string('label')->nullable()->comment('中文说明');
            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
