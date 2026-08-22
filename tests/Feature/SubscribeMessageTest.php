<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Notification;
use App\Models\User;
use App\Services\SubscribeMessageService;
use App\Services\WechatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscribeMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 配置微信小程序配置
        config([
            'services.mini_program' => [
                'app_id' => 'test-app-id',
                'secret' => 'test-secret',
                'feedback_template_id' => 'feedback-tpl-001',
                'announcement_template_id' => 'announcement-tpl-001',
            ],
        ]);
    }

    /**
     * WechatService::sendSubscribeMessage 成功推送
     */
    public function test_send_subscribe_message_success(): void
    {
        // 伪造 access_token 接口
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

        /** @var WechatService $service */
        $service = app(WechatService::class);

        $result = $service->sendSubscribeMessage(
            openid: 'openid-abc',
            templateId: 'tpl-001',
            data: ['thing1' => ['value' => '测试内容']],
            page: 'pages/index',
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['errcode']);
        $this->assertSame('ok', $result['errmsg']);

        Http::assertSentCount(2);
    }

    /**
     * WechatService::sendSubscribeMessage 用户未订阅错误（43101）
     */
    public function test_send_subscribe_message_user_not_subscribed(): void
    {
        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'test-token-123',
                'expires_in' => 7200,
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response([
                'errcode' => 43101,
                'errmsg' => 'user refuse to accept the msg',
            ], 200),
        ]);

        /** @var WechatService $service */
        $service = app(WechatService::class);

        $result = $service->sendSubscribeMessage(
            openid: 'openid-abc',
            templateId: 'tpl-001',
            data: ['thing1' => ['value' => '测试']],
        );

        $this->assertFalse($result['success']);
        $this->assertSame(43101, $result['errcode']);
        $this->assertStringContainsString('用户未订阅', $result['errmsg']);
    }

    /**
     * WechatService token 过期自动刷新重试
     */
    public function test_send_subscribe_message_token_expired_then_retry(): void
    {
        $callCount = 0;
        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'refreshed-token',
                'expires_in' => 7200,
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    // 第一次返回 token 过期
                    return Http::response([
                        'errcode' => 42001,
                        'errmsg' => 'access_token expired',
                    ], 200);
                }
                // 第二次成功
                return Http::response([
                    'errcode' => 0,
                    'errmsg' => 'ok',
                ], 200);
            },
        ]);

        /** @var WechatService $service */
        $service = app(WechatService::class);

        $result = $service->sendSubscribeMessage(
            openid: 'openid-abc',
            templateId: 'tpl-001',
            data: ['thing1' => ['value' => '测试']],
        );

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['errcode']);
    }

    /**
     * 未配置模板 ID 时直接返回失败，不发请求
     */
    public function test_send_subscribe_missing_template_id_no_request(): void
    {
        config(['services.mini_program.feedback_template_id' => '']);

        Http::fake();

        /** @var WechatService $service */
        $service = app(WechatService::class);

        $result = $service->sendSubscribeMessage(
            openid: 'openid-abc',
            templateId: '',
            data: ['thing1' => ['value' => '测试']],
        );

        $this->assertFalse($result['success']);
        $this->assertSame(-2, $result['errcode']);
        Http::assertNothingSent();
    }

    /**
     * 反馈处理后推送：成功场景
     */
    public function test_push_feedback_handled_success(): void
    {
        $user = User::factory()->create(['openid' => 'openid-user-1']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => Feedback::STATUS_PENDING,
            'subscribe_sent' => false,
        ]);

        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'token',
                'expires_in' => 7200,
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response([
                'errcode' => 0,
                'errmsg' => 'ok',
            ], 200),
        ]);

        /** @var SubscribeMessageService $svc */
        $svc = app(SubscribeMessageService::class);
        $svc->pushFeedbackHandled($feedback->fresh());

        $feedback->refresh();
        $this->assertTrue($feedback->subscribe_sent);
        $this->assertNotNull($feedback->subscribe_sent_at);
        $this->assertNotNull($feedback->subscribe_result);

        // 写入了审计日志
        $this->assertDatabaseHas('audit_logs', [
            'type' => 'subscribe_message',
            'module' => 'feedback',
            'action' => 'feedback_subscribe_sent',
            'subject_type' => Feedback::class,
            'subject_id' => $feedback->id,
        ]);
    }

    /**
     * 反馈处理后推送：失败不抛异常（用户未订阅）
     */
    public function test_push_feedback_handled_failure_no_exception(): void
    {
        $user = User::factory()->create(['openid' => 'openid-user-2']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => Feedback::STATUS_RESOLVED,
            'subscribe_sent' => false,
        ]);

        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'token',
                'expires_in' => 7200,
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response([
                'errcode' => 43101,
                'errmsg' => 'user refuse',
            ], 200),
        ]);

        /** @var SubscribeMessageService $svc */
        $svc = app(SubscribeMessageService::class);

        // 不应抛出异常
        $svc->pushFeedbackHandled($feedback->fresh());

        $feedback->refresh();
        $this->assertTrue($feedback->subscribe_sent); // 标记为已尝试
        $result = json_decode($feedback->subscribe_result, true);
        $this->assertSame(43101, $result['errcode']);

        // 写入了失败审计日志
        $this->assertDatabaseHas('audit_logs', [
            'type' => 'subscribe_message',
            'module' => 'feedback',
            'action' => 'feedback_subscribe_failed',
            'subject_type' => Feedback::class,
            'subject_id' => $feedback->id,
        ]);
    }

    /**
     * 重复推送：已推送过的反馈不会再次发送
     */
    public function test_push_feedback_skip_if_already_sent(): void
    {
        $user = User::factory()->create(['openid' => 'openid-user-3']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => Feedback::STATUS_RESOLVED,
            'subscribe_sent' => true, // 已标记为已推送
            'subscribe_sent_at' => now()->subHour(),
        ]);

        Http::fake();

        /** @var SubscribeMessageService $svc */
        $svc = app(SubscribeMessageService::class);
        $svc->pushFeedbackHandled($feedback->fresh());

        Http::assertNothingSent();
    }

    /**
     * 公告发布推送：按范围遍历所有 openid 用户
     */
    public function test_push_announcement_published_iterates_users(): void
    {
        $user1 = User::factory()->create(['openid' => 'openid-1', 'status' => User::STATUS_NORMAL]);
        $user2 = User::factory()->create(['openid' => 'openid-2', 'status' => User::STATUS_NORMAL]);
        User::factory()->create(['openid' => null, 'status' => User::STATUS_NORMAL]); // 无 openid 跳过
        User::factory()->create(['openid' => 'openid-banned', 'status' => User::STATUS_BANNED]); // 被封禁跳过

        $announcement = Announcement::factory()->create([
            'status' => Announcement::STATUS_PUBLISHED,
        ]);

        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'token',
                'expires_in' => 7200,
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response([
                'errcode' => 0,
                'errmsg' => 'ok',
            ], 200),
        ]);

        /** @var SubscribeMessageService $svc */
        $svc = app(SubscribeMessageService::class);
        $svc->pushAnnouncementPublished($announcement);

        // token 请求 + 至少 2 次 send 调用
        $totalRequests = collect(Http::recorded())->count();
        $this->assertGreaterThanOrEqual(3, $totalRequests, '至少 1 次 token + 2 次 send 请求');

        // 审计日志写入
        $this->assertDatabaseHas('audit_logs', [
            'type' => 'subscribe_message',
            'module' => 'announcement',
            'action' => 'announcement_subscribe_published',
            'subject_type' => Announcement::class,
            'subject_id' => $announcement->id,
        ]);
    }

    /**
     * 公告推送：微信接口异常不中断，仍会遍历其他用户
     */
    public function test_push_announcement_exception_does_not_abort(): void
    {
        $user1 = User::factory()->create(['openid' => 'openid-e1', 'status' => User::STATUS_NORMAL]);
        $user2 = User::factory()->create(['openid' => 'openid-e2', 'status' => User::STATUS_NORMAL]);

        $announcement = Announcement::factory()->create([
            'status' => Announcement::STATUS_PUBLISHED,
        ]);

        $sendCallCount = 0;
        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'token',
                'expires_in' => 7200,
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => function () use (&$sendCallCount) {
                $sendCallCount++;
                if ($sendCallCount === 1) {
                    // 第一个用户抛出网络异常
                    throw new \RuntimeException('connection timeout');
                }

                return Http::response(['errcode' => 0, 'errmsg' => 'ok'], 200);
            },
        ]);

        /** @var SubscribeMessageService $svc */
        $svc = app(SubscribeMessageService::class);

        // 不应抛出异常
        $svc->pushAnnouncementPublished($announcement);

        // 两个用户都调用了 send 接口（异常被 catch，继续下一个）
        $this->assertGreaterThanOrEqual(2, $sendCallCount);
    }

    /**
     * 站内通知推送：scope=specified 指定用户
     */
    public function test_push_notification_published_specified_scope(): void
    {
        $target1 = User::factory()->create(['openid' => 'openid-t1', 'status' => User::STATUS_NORMAL]);
        $target2 = User::factory()->create(['openid' => 'openid-t2', 'status' => User::STATUS_NORMAL]);
        $other = User::factory()->create(['openid' => 'openid-other', 'status' => User::STATUS_NORMAL]);

        $notification = Notification::factory()->create([
            'published' => true,
            'scope' => 'specified',
            'targets' => [$target1->id, $target2->id],
            'subscribe_sent' => false,
        ]);

        Http::fake([
            'https://api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'token',
                'expires_in' => 7200,
            ], 200),
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send*' => Http::response([
                'errcode' => 0,
                'errmsg' => 'ok',
            ], 200),
        ]);

        /** @var SubscribeMessageService $svc */
        $svc = app(SubscribeMessageService::class);
        $svc->pushNotificationPublished($notification->fresh());

        $notification->refresh();
        $this->assertTrue($notification->subscribe_sent);
        $result = json_decode($notification->subscribe_result, true);
        $this->assertSame(2, $result['total'] ?? 0);
        $this->assertSame(2, $result['success'] ?? 0);
        $this->assertSame(0, $result['failed'] ?? 0);
    }

    /**
     * 业务层调用：推送失败时不阻塞主逻辑（反馈处理更新成功）
     * 模拟 SubscribeMessageService 抛出异常，外层 try-catch 应吞掉
     */
    public function test_business_layer_handles_push_exception_gracefully(): void
    {
        $user = User::factory()->create(['openid' => 'openid-ex']);
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => Feedback::STATUS_PENDING,
        ]);

        // 模拟微信接口直接抛异常
        Http::fake(function () {
            throw new \RuntimeException('DNS resolution failed');
        });

        // 业务层处理（含推送），不应抛异常
        $exception = null;
        try {
            $feedback->update([
                'status' => Feedback::STATUS_RESOLVED,
                'handled_by' => null,
                'handled_at' => now(),
            ]);

            try {
                app(SubscribeMessageService::class)->pushFeedbackHandled($feedback->fresh());
            } catch (\Throwable $e) {
                $exception = $e;
            }
        } catch (\Throwable $outer) {
            $exception = $outer;
        }

        $this->assertNull($exception, '业务处理因推送异常被中断');
        $this->assertSame(Feedback::STATUS_RESOLVED, $feedback->fresh()->status);
    }
}
