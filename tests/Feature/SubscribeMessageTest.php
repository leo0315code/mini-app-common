<?php

namespace Tests\Feature;

use App\Jobs\AnnouncementPublishedJob;
use App\Jobs\FeedbackHandledJob;
use App\Jobs\NotificationPublishedJob;
use App\Jobs\SendSubscribeMessageToUserJob;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Notification;
use App\Models\SubscribeMessageFailure;
use App\Models\User;
use App\Services\SubscribeMessageService;
use App\Services\WechatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscribeMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mini_program' => [
                'app_id' => 'test-app-id',
                'secret' => 'test-secret',
                'feedback_template_id' => 'feedback-tpl-001',
                'announcement_template_id' => 'announcement-tpl-001',
            ],
        ]);
    }

    // ---------- WechatService 基础推送 ----------

    public function test_wechat_service_send_success(): void
    {
        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'test-token-123',
                'expires_in' => 7200,
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response([
                'errcode' => 0,
                'errmsg' => 'ok',
            ], 200),
        ]);

        $service = app(WechatService::class);
        $result = $service->sendSubscribeMessage(
            openid: 'openid-abc',
            templateId: 'tpl-001',
            data: ['thing1' => ['value' => '测试']],
            page: 'pages/index',
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['errcode']);
    }

    public function test_wechat_service_token_expired_retry(): void
    {
        $count = 0;
        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'refreshed-token',
                'expires_in' => 7200,
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => function () use (&$count) {
                $count++;
                if ($count === 1) {
                    return Http::response(['errcode' => 42001, 'errmsg' => 'expired'], 200);
                }

                return Http::response(['errcode' => 0, 'errmsg' => 'ok'], 200);
            },
        ]);

        $result = app(WechatService::class)->sendSubscribeMessage(
            openid: 'openid-abc',
            templateId: 'tpl-001',
            data: ['thing1' => ['value' => 'x']],
        );

        $this->assertTrue($result['success']);
    }

    public function test_wechat_service_missing_template_no_request(): void
    {
        config(['services.mini_program.feedback_template_id' => '']);
        Http::fake();
        $result = app(WechatService::class)->sendSubscribeMessage('o', '', []);
        $this->assertFalse($result['success']);
        $this->assertSame(-2, $result['errcode']);
        Http::assertNothingSent();
    }

    // ---------- Service 层 -> Job 派发（Queue::fake 验证入队） ----------

    public function test_service_push_feedback_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create(['openid' => 'openid-u1']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => Feedback::STATUS_RESOLVED,
            'subscribe_sent' => false,
        ]);

        app(SubscribeMessageService::class)->pushFeedbackHandled($feedback->fresh());

        Queue::assertPushed(FeedbackHandledJob::class, function (FeedbackHandledJob $job) use ($feedback) {
            return $job->feedbackId === $feedback->id;
        });

        // Service 层 dispatch 后会标记 queued 状态
        $feedback->refresh();
        $res = json_decode((string) $feedback->subscribe_result, true);
        $this->assertTrue($res['queued'] ?? false);
    }

    public function test_service_push_feedback_skip_already_sent(): void
    {
        Queue::fake();
        $user = User::factory()->create(['openid' => 'openid-u1']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'subscribe_sent' => true, // 已推送
        ]);

        app(SubscribeMessageService::class)->pushFeedbackHandled($feedback->fresh());
        Queue::assertNotPushed(FeedbackHandledJob::class);
    }

    public function test_service_push_feedback_skip_no_openid(): void
    {
        Queue::fake();
        $user = User::factory()->create(['openid' => null]);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'subscribe_sent' => false,
        ]);

        app(SubscribeMessageService::class)->pushFeedbackHandled($feedback->fresh());
        Queue::assertNotPushed(FeedbackHandledJob::class);

        $feedback->refresh();
        $this->assertNotNull($feedback->subscribe_result);
        $res = json_decode((string) $feedback->subscribe_result, true);
        $this->assertTrue($res['skipped'] ?? false);
    }

    public function test_service_push_announcement_dispatches_job(): void
    {
        Queue::fake();
        $ann = Announcement::factory()->create(['status' => Announcement::STATUS_PUBLISHED]);

        app(SubscribeMessageService::class)->pushAnnouncementPublished($ann);
        Queue::assertPushed(AnnouncementPublishedJob::class, fn ($j) => $j->announcementId === $ann->id);
    }

    public function test_service_push_notification_dispatches_job(): void
    {
        Queue::fake();
        $notif = Notification::factory()->create([
            'published' => true,
            'scope' => 'registered',
            'subscribe_sent' => false,
        ]);

        app(SubscribeMessageService::class)->pushNotificationPublished($notif->fresh());
        Queue::assertPushed(NotificationPublishedJob::class, fn ($j) => $j->notificationId === $notif->id);
    }

    public function test_service_business_exception_does_not_abort(): void
    {
        // 模拟 dispatch 抛异常（Service 内部应吞掉并降级同步，不抛给上层）
        Queue::fake();
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('redis connection down'));

        $user = User::factory()->create(['openid' => 'openid-u1']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'subscribe_sent' => false,
            'status' => Feedback::STATUS_RESOLVED,
        ]);

        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response(['access_token' => 't', 'expires_in' => 7200], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response(['errcode' => 0, 'errmsg' => 'ok'], 200),
        ]);

        $thrown = null;
        try {
            app(SubscribeMessageService::class)->pushFeedbackHandled($feedback->fresh());
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown, 'Service 派发异常不应冒泡到业务层');
        $this->assertSame(Feedback::STATUS_RESOLVED, $feedback->fresh()->status);
    }

    // ---------- Job::handle 执行（不 fake Queue，走 sync，验证实际 HTTP 调用） ----------

    public function test_feedback_handled_job_handle_success_writes_result(): void
    {
        // 不用 Queue::fake，sync driver 下 dispatch 即执行
        $user = User::factory()->create(['openid' => 'openid-job-1']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => Feedback::STATUS_RESOLVED,
            'subscribe_sent' => false,
            'handle_note' => '已经处理好了',
        ]);

        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response(['access_token' => 't', 'expires_in' => 7200], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response(['errcode' => 0, 'errmsg' => 'ok'], 200),
        ]);

        // 直接执行 Job handle
        $job = new FeedbackHandledJob($feedback->id);
        $job->handle(app(WechatService::class));

        $feedback->refresh();
        $this->assertTrue($feedback->subscribe_sent);
        $this->assertNotNull($feedback->subscribe_sent_at);

        $result = json_decode((string) $feedback->subscribe_result, true);
        $this->assertSame(0, $result['errcode']);
    }

    public function test_feedback_handled_job_user_refuse_writes_failure_table_no_retry(): void
    {
        $user = User::factory()->create(['openid' => 'openid-job-2']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => Feedback::STATUS_RESOLVED,
            'subscribe_sent' => false,
        ]);

        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response(['access_token' => 't', 'expires_in' => 7200], 200),
            // 用户未订阅 -> 业务级错误，不重试
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response([
                'errcode' => 43101,
                'errmsg' => 'user refuse to accept the msg',
            ], 200),
        ]);

        $job = new FeedbackHandledJob($feedback->id);
        try {
            $job->handle(app(WechatService::class));
            $jobThrew = false;
        } catch (\RuntimeException $e) {
            $jobThrew = true;
        }

        $this->assertFalse($jobThrew, '43101 属于不重试业务错误，不应抛出异常触发队列重试');

        // subscribe_message_failures 表有记录
        $this->assertDatabaseHas('subscribe_message_failures', [
            'scene' => 'feedback_handled',
            'subject_type' => Feedback::class,
            'subject_id' => $feedback->id,
            'openid' => $user->openid,
            'last_errcode' => 43101,
        ]);
    }

    public function test_feedback_handled_job_failed_callback_writes_failure(): void
    {
        $user = User::factory()->create(['openid' => 'openid-fail']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'subscribe_sent' => false,
        ]);

        $job = new FeedbackHandledJob($feedback->id);
        // 手动调 failed()，模拟队列最终失败
        $job->failed(new \RuntimeException('max attempts exceeded'));

        $this->assertDatabaseHas('subscribe_message_failures', [
            'scene' => 'feedback_handled',
            'subject_id' => $feedback->id,
            'last_errcode' => -999,
        ]);

        // feedback subscribe_sent 最终也会被标记，避免再次 dispatch 无限循环
        $feedback->refresh();
        $this->assertTrue($feedback->subscribe_sent);
    }

    public function test_feedback_handled_job_already_sent_skips_http(): void
    {
        $user = User::factory()->create(['openid' => 'openid-dedup']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'subscribe_sent' => true, // 已推送
        ]);

        Http::fake();

        $job = new FeedbackHandledJob($feedback->id);
        $job->handle(app(WechatService::class));

        Http::assertNothingSent();
    }

    public function test_send_to_user_job_unique_id_distinct_by_scene_and_user(): void
    {
        $u1 = User::factory()->create(['id' => 100]);
        $u2 = User::factory()->create(['id' => 101]);
        $ann = Announcement::factory()->create(['id' => 1]);

        $j1 = new SendSubscribeMessageToUserJob(
            scene: 'announcement_published',
            subject: $ann,
            openid: 'openid-a',
            templateId: 'tpl',
            data: [],
        );
        $j2 = new SendSubscribeMessageToUserJob(
            scene: 'announcement_published',
            subject: $ann,
            openid: 'openid-a',  // 同场景+同公告+同openid → 同uniqueId
            templateId: 'tpl',
            data: [],
        );
        $j3 = new SendSubscribeMessageToUserJob(
            scene: 'announcement_published',
            subject: $ann,
            openid: 'openid-b',  // 不同 openid → 不同 uniqueId
            templateId: 'tpl',
            data: [],
        );

        $this->assertSame($j1->uniqueId(), $j2->uniqueId());
        $this->assertNotSame($j1->uniqueId(), $j3->uniqueId());
    }

    public function test_announcement_job_chunk_dispatches_send_to_user_job(): void
    {
        // 先把所有其它用户 openid 置空，避免 seeder 或其它测试遗留用户
        User::query()->whereNotNull('id')->update([
            'openid' => null,
            'status' => User::STATUS_BANNED,
        ]);

        Queue::fake([SendSubscribeMessageToUserJob::class]);

        $u1 = User::factory()->create(['openid' => 'test-a1', 'status' => User::STATUS_NORMAL]);
        $u2 = User::factory()->create(['openid' => 'test-a2', 'status' => User::STATUS_NORMAL]);
        User::factory()->create(['openid' => null, 'status' => User::STATUS_NORMAL]);
        User::factory()->create(['openid' => 'test-a3', 'status' => User::STATUS_BANNED]);

        $ann = Announcement::factory()->create(['status' => Announcement::STATUS_PUBLISHED]);

        $job = new AnnouncementPublishedJob($ann->id);
        $job->handle();

        // 目标测试用户必须被派发（QueueFake 中 assertPushed 回调接收的是 Job 实例本身）
        Queue::assertPushed(
            SendSubscribeMessageToUserJob::class,
            fn (SendSubscribeMessageToUserJob $j) => $j->openid === 'test-a1'
                && $j->scene === 'announcement_published'
                && $j->subjectType === Announcement::class
                && $j->subjectId === $ann->id,
        );
        Queue::assertPushed(
            SendSubscribeMessageToUserJob::class,
            fn (SendSubscribeMessageToUserJob $j) => $j->openid === 'test-a2',
        );

        // 被封禁用户不应被派发
        Queue::assertNotPushed(
            SendSubscribeMessageToUserJob::class,
            fn (SendSubscribeMessageToUserJob $j) => $j->openid === 'test-a3',
        );
    }

    public function test_notification_job_scope_specified_dispatches_targets_only(): void
    {
        Queue::fake([SendSubscribeMessageToUserJob::class]);

        $t1 = User::factory()->create(['openid' => 't1', 'status' => User::STATUS_NORMAL]);
        $t2 = User::factory()->create(['openid' => 't2', 'status' => User::STATUS_NORMAL]);
        $other = User::factory()->create(['openid' => 'other', 'status' => User::STATUS_NORMAL]);

        $notif = Notification::factory()->create([
            'published' => true,
            'scope' => 'specified',
            'targets' => [$t1->id, $t2->id],
            'subscribe_sent' => false,
        ]);

        $job = new NotificationPublishedJob($notif->id);
        $job->handle();

        Queue::assertPushed(SendSubscribeMessageToUserJob::class, 2);

        $notif->refresh();
        $this->assertTrue($notif->subscribe_sent);
        $res = json_decode((string) $notif->subscribe_result, true);
        $this->assertSame(2, $res['total'] ?? 0);
        $this->assertSame(2, $res['dispatched'] ?? 0);
    }

    // ---------- 最终失败表 SubscribeMessageFailure 关联 ----------

    public function test_subscribe_message_failure_model_morph_relation(): void
    {
        $feedback = Feedback::factory()->create();
        $failure = SubscribeMessageFailure::query()->create([
            'scene' => 'feedback_handled',
            'subject_type' => Feedback::class,
            'subject_id' => $feedback->id,
            'openid' => 'o1',
            'template_id' => 'tpl',
            'attempts' => 3,
            'last_errcode' => 43101,
            'last_errmsg' => 'user refuse',
            'last_attempted_at' => now(),
        ]);

        $this->assertTrue($failure->subject->is($feedback));
    }

    // ---------- 异常容错：接口极端异常时业务流程不中断 ----------

    public function test_business_handler_not_aborted_when_wechat_api_down(): void
    {
        $user = User::factory()->create(['openid' => 'openid-dns']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => Feedback::STATUS_PENDING,
            'subscribe_sent' => false,
        ]);

        // 模拟微信 API 直接抛异常（DNS 故障等）
        Http::fake(function () {
            throw new \RuntimeException('DNS resolution failed');
        });

        // 队列 sync 模式下，dispatch 直接执行 Job handle。
        // 但 Service 层有 try-catch 保护，不应该冒泡。
        $thrown = null;
        try {
            $feedback->update([
                'status' => Feedback::STATUS_RESOLVED,
                'handled_at' => now(),
            ]);
            app(SubscribeMessageService::class)->pushFeedbackHandled($feedback->fresh());
        } catch (\Throwable $outer) {
            $thrown = $outer;
        }

        $this->assertNull($thrown, '业务处理不应被推送异常中断');
        $this->assertSame(Feedback::STATUS_RESOLVED, $feedback->fresh()->status);
    }
}
