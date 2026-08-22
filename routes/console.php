<?php

use App\Console\Commands\PublishScheduled;
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
