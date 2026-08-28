<?php

namespace App\Services;

use App\Jobs\AnnouncementPublishedJob;
use App\Jobs\FeedbackHandledJob;
use App\Jobs\NotificationPublishedJob;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Notification;
use App\Support\SubscribeMessageTemplate;
use Illuminate\Support\Facades\Log;

/**
 * 微信订阅消息推送业务服务（异步队列版）。
 *
 * 三个公开方法只做「快速前置检查 + dispatch Job」，
 * 真正执行在 Job::handle() 里，由队列 worker 异步消费。
 *
 * 核心原则：
 *   - dispatch 失败也绝不抛异常、不阻塞主业务流程（try-catch 吞掉）
 *   - 明显不会成功的场景（无 openid、未配模板 ID、已推送过），
 *     直接标记 subscribe_sent/subscribe_result，不入队浪费资源
 */
class SubscribeMessageService
{
    public function __construct(
        protected WechatService $wechatService,
    ) {
    }

    /**
     * 反馈处理完成后，异步向提交用户推送处理结果通知。
     */
    public function pushFeedbackHandled(Feedback $feedback): void
    {
        // 快速检查 1：已推送过（或已入队）就跳过，避免重复入队
        if ($feedback->subscribe_sent) {
            return;
        }

        try {
            // 快速检查 2：无关联用户或 openid 为空 → 直接标记，不入队
            $user = $feedback->user;
            if (! $user || empty($user->openid)) {
                $this->markSkipped($feedback, '无关联用户或 openid 为空');

                return;
            }

            // 快速检查 3：模板 ID 未配置 → 直接标记，不入队
            $templateId = config('services.mini_program.feedback_template_id');
            if (empty($templateId)) {
                $this->markSkipped($feedback, '反馈处理模板 ID 未配置');

                return;
            }

            // 快速检查全部通过，派发 Job（ShouldBeUnique 按 feedback_id 去重）
            try {
                FeedbackHandledJob::dispatch($feedback->id);

                // 标记为「已入队待执行」—— 即便 Job 执行失败，也不会被 Service 层再次 dispatch
                // 由 Job 内部在最终失败时把结果写回 subscribe_result
                $feedback->forceFill([
                    'subscribe_sent' => false,
                    'subscribe_sent_at' => null,
                    'subscribe_result' => json_encode([
                        'queued' => true,
                        'dispatched_at' => now()->toISOString(),
                        'note' => '已进入队列，异步推送中',
                    ], JSON_UNESCAPED_UNICODE),
                ])->save();

                $this->writeAudit('feedback_subscribe_queued', $feedback, [
                    'user_id' => $user->id,
                    'openid_masked' => SubscribeMessageTemplate::maskOpenid($user->openid),
                ]);
            } catch (\Throwable $e) {
                // dispatch 本身失败（比如队列连接异常），降级为同步尝试一次；
                // 若同步再失败，就按错误标记，不影响业务。
                Log::warning('[订阅消息] FeedbackHandledJob 入队失败，降级同步推送: ' . $e->getMessage(), [
                    'feedback_id' => $feedback->id,
                ]);
                $this->fallbackSyncFeedback($feedback);
            }
        } catch (\Throwable $e) {
            // 兜底：任何异常吞掉，不影响外层业务
            Log::error('[订阅消息] 反馈处理派发异常: ' . $e->getMessage(), [
                'feedback_id' => $feedback->id,
                'trace' => $e->getTraceAsString(),
            ]);
            try {
                $this->markError($feedback, $e->getMessage());
            } catch (\Throwable $_) {
            }
        }
    }

    /**
     * 发布新公告后，异步按范围推送给用户。
     */
    public function pushAnnouncementPublished(Announcement $announcement): void
    {
        try {
            $templateId = config('services.mini_program.announcement_template_id');
            if (empty($templateId)) {
                Log::info('[订阅消息] 公告推送跳过：模板 ID 未配置', ['announcement_id' => $announcement->id]);

                return;
            }

            try {
                AnnouncementPublishedJob::dispatch($announcement->id);

                $this->writeAudit('announcement_subscribe_queued', $announcement, [
                    'template_id_configured' => true,
                ]);
            } catch (\Throwable $e) {
                // 队列入队失败：同步遍历（降级），但保持 try-catch 不影响主流程
                Log::warning('[订阅消息] AnnouncementPublishedJob 入队失败，降级同步遍历: ' . $e->getMessage(), [
                    'announcement_id' => $announcement->id,
                ]);
                $this->fallbackSyncAnnouncement($announcement);
            }
        } catch (\Throwable $e) {
            Log::error('[订阅消息] 公告派发异常: ' . $e->getMessage(), [
                'announcement_id' => $announcement->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 发布新通知后，异步按范围推送给用户（站内通知）。
     */
    public function pushNotificationPublished(Notification $notification): void
    {
        if ($notification->subscribe_sent) {
            return;
        }

        try {
            $templateId = config('services.mini_program.announcement_template_id');
            if (empty($templateId)) {
                $this->markSkippedNotification($notification, '公告/通知模板 ID 未配置');

                return;
            }

            try {
                NotificationPublishedJob::dispatch($notification->id);

                // 标记为「入队中」—— 实际派发结果由 Job 在执行时写回
                $notification->forceFill([
                    'subscribe_sent' => false,
                    'subscribe_sent_at' => null,
                    'subscribe_result' => json_encode([
                        'queued' => true,
                        'dispatched_at' => now()->toISOString(),
                        'note' => '已进入队列，Job 会按 scope 展开接收人并派发子任务',
                    ], JSON_UNESCAPED_UNICODE),
                ])->save();

                $this->writeAudit('notification_subscribe_queued', $notification, [
                    'scope' => $notification->scope,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[订阅消息] NotificationPublishedJob 入队失败，降级同步推送: ' . $e->getMessage(), [
                    'notification_id' => $notification->id,
                ]);
                $this->fallbackSyncNotification($notification);
            }
        } catch (\Throwable $e) {
            Log::error('[订阅消息] 站内通知派发异常: ' . $e->getMessage(), [
                'notification_id' => $notification->id,
                'trace' => $e->getTraceAsString(),
            ]);
            try {
                $this->markErrorNotification($notification, $e->getMessage());
            } catch (\Throwable $_) {
            }
        }
    }

    // ---------- 降级同步（当队列 dispatch 失败时的兜底） ----------

    protected function fallbackSyncFeedback(Feedback $feedback): void
    {
        try {
            $user = $feedback->user;
            if (! $user || empty($user->openid)) {
                $this->markSkipped($feedback, '降级同步-无 openid');

                return;
            }
            $templateId = (string) config('services.mini_program.feedback_template_id');
            if ($templateId === '') {
                $this->markSkipped($feedback, '降级同步-无模板ID');

                return;
            }

            $statusText = match ($feedback->status) {
                Feedback::STATUS_RESOLVED => '已解决',
                Feedback::STATUS_REJECTED => '已驳回',
                Feedback::STATUS_PROCESSING => '处理中',
                default => $feedback->status,
            };
            $handleNote = SubscribeMessageTemplate::truncate($feedback->handle_note ?: '（无处理备注）');
            $feedbackContent = SubscribeMessageTemplate::truncate($feedback->content);
            $data = [
                'thing1' => ['value' => $feedbackContent],
                'phrase2' => ['value' => $statusText],
                'thing3' => ['value' => $handleNote],
                'time4' => ['value' => now()->format('Y-m-d H:i')],
            ];

            $result = $this->wechatService->sendSubscribeMessage(
                openid: $user->openid,
                templateId: $templateId,
                data: $data,
                page: 'pages/feedback/detail?id=' . $feedback->id,
            );
            $this->markResult($feedback, $result);
            $this->writeAudit(
                $result['success'] ? 'feedback_subscribe_sent' : 'feedback_subscribe_failed',
                $feedback,
                array_merge($result, ['fallback_sync' => true]),
            );
        } catch (\Throwable $e) {
            $this->markError($feedback, '降级同步失败: ' . $e->getMessage());
        }
    }

    protected function fallbackSyncAnnouncement(Announcement $announcement): void
    {
        try {
            $templateId = (string) config('services.mini_program.announcement_template_id');
            if ($templateId === '') {
                return;
            }

            $typeText = match ($announcement->type) {
                Announcement::TYPE_NOTICE => '通知',
                Announcement::TYPE_ACTIVITY => '活动',
                Announcement::TYPE_UPDATE => '版本更新',
                default => '公告',
            };
            $title = SubscribeMessageTemplate::truncate($announcement->title);
            $content = SubscribeMessageTemplate::truncate($announcement->content);
            $data = [
                'thing1' => ['value' => $typeText],
                'thing2' => ['value' => $title],
                'thing3' => ['value' => $content ?: '（点击查看详情）'],
                'time4' => ['value' => now()->format('Y-m-d H:i')],
            ];

            $users = \App\Models\User::query()->whereNotNull('openid')->active()->limit(500)->get();
            $ok = 0;
            $fail = 0;
            foreach ($users as $u) {
                try {
                    $r = $this->wechatService->sendSubscribeMessage(
                        openid: $u->openid,
                        templateId: $templateId,
                        data: $data,
                        page: 'pages/announcement/detail?id=' . $announcement->id,
                    );
                    if ($r['success']) {
                        $ok++;
                    } else {
                        $fail++;
                    }
                } catch (\Throwable $_) {
                    $fail++;
                }
            }
            $this->writeAudit('announcement_subscribe_published', $announcement, [
                'fallback_sync' => true,
                'limited_to' => 500,
                'total' => $users->count(),
                'success' => $ok,
                'failed' => $fail,
            ]);
        } catch (\Throwable $e) {
            Log::error('[订阅消息] 公告降级同步失败: ' . $e->getMessage(), ['announcement_id' => $announcement->id]);
        }
    }

    protected function fallbackSyncNotification(Notification $notification): void
    {
        try {
            $templateId = (string) config('services.mini_program.announcement_template_id');
            if ($templateId === '') {
                $this->markSkippedNotification($notification, '降级同步-无模板ID');

                return;
            }

            $baseQuery = \App\Models\User::query()->whereNotNull('openid')->active();
            if ($notification->scope === 'specified') {
                $baseQuery->whereIn('id', $notification->targets ?? []);
            }

            $title = SubscribeMessageTemplate::truncate($notification->title);
            $body = SubscribeMessageTemplate::truncate($notification->body);
            $data = [
                'thing1' => ['value' => '站内通知'],
                'thing2' => ['value' => $title],
                'thing3' => ['value' => $body ?: '（点击查看详情）'],
                'time4' => ['value' => now()->format('Y-m-d H:i')],
            ];

            $users = $baseQuery->limit(500)->get();
            $ok = 0;
            $fail = 0;
            foreach ($users as $u) {
                try {
                    $r = $this->wechatService->sendSubscribeMessage(
                        openid: $u->openid,
                        templateId: $templateId,
                        data: $data,
                        page: 'pages/notification/detail?id=' . $notification->id,
                    );
                    if ($r['success']) {
                        $ok++;
                    } else {
                        $fail++;
                    }
                } catch (\Throwable $_) {
                    $fail++;
                }
            }

            $notification->forceFill([
                'subscribe_sent' => true,
                'subscribe_sent_at' => now(),
                'subscribe_result' => json_encode([
                    'fallback_sync' => true,
                    'limited_to' => 500,
                    'total' => $users->count(),
                    'success' => $ok,
                    'failed' => $fail,
                ], JSON_UNESCAPED_UNICODE),
            ])->save();

            $this->writeAudit('notification_subscribe_sent', $notification, [
                'fallback_sync' => true,
                'total' => $users->count(),
                'success' => $ok,
                'failed' => $fail,
            ]);
        } catch (\Throwable $e) {
            try {
                $this->markErrorNotification($notification, '降级同步失败: ' . $e->getMessage());
            } catch (\Throwable $_) {
            }
        }
    }

    // ---------- 辅助方法 ----------

    protected function markResult(Feedback $feedback, array $result): void
    {
        $feedback->forceFill([
            'subscribe_sent' => true,
            'subscribe_sent_at' => now(),
            'subscribe_result' => json_encode($result, JSON_UNESCAPED_UNICODE),
        ])->save();
    }

    protected function markSkipped(Feedback $feedback, string $reason): void
    {
        $feedback->forceFill([
            'subscribe_sent' => false,
            'subscribe_sent_at' => now(),
            'subscribe_result' => json_encode(['skipped' => true, 'reason' => $reason], JSON_UNESCAPED_UNICODE),
        ])->save();
    }

    protected function markError(Feedback $feedback, string $message): void
    {
        $feedback->forceFill([
            'subscribe_sent' => false,
            'subscribe_sent_at' => now(),
            'subscribe_result' => json_encode(['error' => true, 'message' => $message], JSON_UNESCAPED_UNICODE),
        ])->save();
    }

    protected function markSkippedNotification(Notification $notification, string $reason): void
    {
        $notification->forceFill([
            'subscribe_sent' => false,
            'subscribe_sent_at' => now(),
            'subscribe_result' => json_encode(['skipped' => true, 'reason' => $reason], JSON_UNESCAPED_UNICODE),
        ])->save();
    }

    protected function markErrorNotification(Notification $notification, string $message): void
    {
        $notification->forceFill([
            'subscribe_sent' => false,
            'subscribe_sent_at' => now(),
            'subscribe_result' => json_encode(['error' => true, 'message' => $message], JSON_UNESCAPED_UNICODE),
        ])->save();
    }

    /**
     * 写入审计日志（静默失败）。
     */
    protected function writeAudit(string $action, object $model, array $meta): void
    {
        try {
            AuditLog::query()->create([
                'type' => 'subscribe_message',
                'module' => SubscribeMessageTemplate::resolveModule($model),
                'action' => $action,
                'description' => '微信订阅消息推送',
                'subject_type' => $model::class,
                'subject_id' => $model->id ?? null,
                'new_data' => $meta,
                'user_id' => null,
                'ip' => request()?->ip(),
                'url' => request()?->url(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[订阅消息] 审计日志写入失败: ' . $e->getMessage());
        }
    }
}
