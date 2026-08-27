<?php

use App\Console\Commands\PublishScheduled;
use App\Console\Commands\RetryFailedSubscribeMessages;
use App\Console\Commands\SyncArticleViews;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| 定时任务调度
|--------------------------------------------------------------------------
|
| 部署时需在服务器 crontab 添加：
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
| Docker 环境在 entrypoint 中通过 cron 或 supervisor 启动 schedule:work 亦可。
|
*/

Schedule::command(PublishScheduled::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('发布计划到期的公告与站内通知');

// 订阅消息失败自动重试：每 5 分钟补发前 100 条未解决失败记录（幂等，可随时手动执行）
Schedule::command(RetryFailedSubscribeMessages::class, ['--limit=100'])
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('重试失败表中未解决的订阅消息');

// 文章浏览数 Redis 计数器落库：每 5 分钟累加一次
Schedule::command(SyncArticleViews::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->description('Redis 文章浏览计数同步到数据库');
