<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->boolean('subscribe_sent')->default(false)->after('handled_at')->comment('微信订阅消息是否已推送');
            $table->timestamp('subscribe_sent_at')->nullable()->after('subscribe_sent')->comment('微信订阅消息推送时间');
            $table->text('subscribe_result')->nullable()->after('subscribe_sent_at')->comment('微信订阅消息推送结果（JSON）');
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropColumn(['subscribe_sent', 'subscribe_sent_at', 'subscribe_result']);
        });
    }
};
