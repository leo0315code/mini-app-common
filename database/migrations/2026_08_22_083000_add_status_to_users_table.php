<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 用户封禁支持：状态 / 封禁时间 / 封禁原因。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')
                ->default('normal')
                ->comment('账户状态：normal 正常 / banned 已封禁')
                ->after('gender');
            $table->timestamp('banned_at')->nullable()->comment('封禁时间')->after('status');
            $table->string('ban_reason')->nullable()->comment('封禁原因')->after('banned_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'banned_at', 'ban_reason']);
        });
    }
};
