<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-11 Token 设备信息：为 Sanctum personal_access_tokens 增加设备标识，
 * 用于「我的设备 / 会话管理 / 一键踢下线」。
 * 仅追加两列，保持 Sanctum 原表结构兼容。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('device_name', 255)->nullable()->after('name')->comment('设备名称/型号');
            $table->text('user_agent')->nullable()->after('device_name')->comment('客户端 UA');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['device_name', 'user_agent']);
        });
    }
};
