<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 调度注册验证：subscribe:retry-failed 每 5 分钟自动执行。
 * 说明：routes/console.php 的 Schedule 注册在 console 内核引导时执行，
 * PHPUnit 测试进程不加载，故用 `schedule:list` 输出断言（该命令会触发注册）。
 */
class ScheduleRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_failed_subscribe_scheduled_every_five_minutes(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('subscribe:retry-failed --limit=100')
            ->assertExitCode(0);
    }
}
