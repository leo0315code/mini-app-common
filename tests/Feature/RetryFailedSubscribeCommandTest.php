<?php

namespace Tests\Feature;

use App\Jobs\SendSubscribeMessageToUserJob;
use App\Models\Announcement;
use App\Models\SubscribeMessageFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 订阅消息失败重试命令（subscribe:retry-failed）：
 * 预览/入队/放弃分支，以及 Job 成功后回写 resolved_at。
 */
class RetryFailedSubscribeCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'queue.default' => 'database',
            'services.mini_program' => [
                'app_id' => 'wx-test-appid',
                'secret' => 'wx-test-secret',
            ],
        ]);
    }

    private function makeFailure(array $overrides = []): SubscribeMessageFailure
    {
        $announcement = Announcement::factory()->create();

        return SubscribeMessageFailure::factory()->create(array_merge([
            'scene' => 'announcement_published',
            'subject_type' => Announcement::class,
            'subject_id' => $announcement->id,
            'openid' => 'oTEST_'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'template_id' => 'TPL123',
            'payload' => ['data' => ['thing1' => ['value' => 'x']], 'page' => 'pages/index'],
            'page' => 'pages/index',
            'attempts' => 1,
            'last_errcode' => 40001,
            'last_errmsg' => 'invalid credential',
            'last_attempted_at' => now()->subHour(),
        ], $overrides));
    }

    public function test_no_pending_returns_success(): void
    {
        $this->artisan('subscribe:retry-failed')
            ->expectsOutputToContain('没有待重试')
            ->assertExitCode(0);
    }

    public function test_dry_run_does_not_dispatch(): void
    {
        Queue::fake();

        $failure = $this->makeFailure();

        $this->artisan('subscribe:retry-failed', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
        $this->assertSame(1, $failure->fresh()->attempts, 'dry-run 不应增加重试次数');
    }

    public function test_dispatches_pending_and_increments_attempts(): void
    {
        Queue::fake();

        $failure = $this->makeFailure();

        $this->artisan('subscribe:retry-failed')
            ->expectsOutputToContain('入队重试 1 条')
            ->assertExitCode(0);

        Queue::assertPushed(SendSubscribeMessageToUserJob::class, function ($job) use ($failure) {
            return $job->openid === $failure->openid
                && $job->templateId === $failure->template_id
                && $job->scene === $failure->scene;
        });

        $this->assertSame(2, $failure->fresh()->attempts, '重试次数应 +1');
    }

    public function test_business_error_code_is_abandoned_not_dispatched(): void
    {
        Queue::fake();

        // 43101 = 用户未订阅/取消订阅，业务级不可重试
        $failure = $this->makeFailure(['last_errcode' => 43101, 'last_errmsg' => 'user refuse']);

        $this->artisan('subscribe:retry-failed')
            ->expectsOutputToContain('放弃')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
        $this->assertNotNull($failure->fresh()->resolved_at, '业务级错误应标记已解决');
        $this->assertStringContainsString('自动放弃', $failure->fresh()->resolved_note);
    }

    public function test_max_attempts_reached_records_are_skipped(): void
    {
        Queue::fake();

        $failure = $this->makeFailure(['attempts' => 3]); // max-attempts 默认 3

        $this->artisan('subscribe:retry-failed')
            ->expectsOutputToContain('没有待重试')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_job_success_marks_pending_failure_resolved(): void
    {
        config(['queue.default' => 'sync']); // dispatch 即执行

        $failure = $this->makeFailure(['last_errcode' => 40001]);

        // 直接执行 Job（sync 队列下 dispatch 立即运行）；微信接口调用会失败，
        // 因此这里改用 Job 成功分支验证：先 mock WechatService 返回成功
        $mock = $this->mock(\App\Services\WechatService::class, function ($mock): void {
            $mock->shouldReceive('sendSubscribeMessage')
                ->once()
                ->andReturn([
                    'success' => true,
                    'errcode' => 0,
                    'errmsg' => 'ok',
                    'raw' => [],
                ]);
        });

        $job = new SendSubscribeMessageToUserJob(
            scene: $failure->scene,
            subject: Announcement::find($failure->subject_id),
            openid: $failure->openid,
            templateId: $failure->template_id,
            data: $failure->payload['data'] ?? [],
            page: $failure->payload['page'] ?? $failure->page,
        );

        $job->handle($mock);

        $this->assertNotNull($failure->fresh()->resolved_at, '发送成功后失败记录应标记已解决');
        $this->assertStringContainsString('重试成功', $failure->fresh()->resolved_note);
    }
}
