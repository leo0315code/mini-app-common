<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 微信订阅消息推送业务服务。
 *
 * 核心原则：推送失败不抛异常、不阻塞主业务流程，错误写入日志 + audit。
 */
class SubscribeMessageService
{
    public function __construct(
        protected WechatService $wechatService,
    ) {
    }

    /**
     * 反馈处理完成后，向提交用户推送处理结果通知。
     */
    public function pushFeedbackHandled(Feedback $feedback): void
    {
        // 已推送过就跳过
        if ($feedback->subscribe_sent) {
            return;
        }

        try {
            $user = $feedback->user;
            if (! $user || empty($user->openid)) {
                $this->markSkipped($feedback, '无关联用户或 openid 为空');

                return;
            }

            $templateId = config('services.mini_program.feedback_template_id');
            if (empty($templateId)) {
                $this->markSkipped($feedback, '反馈处理模板 ID 未配置');

                return;
            }

            $statusText = match ($feedback->status) {
                Feedback::STATUS_RESOLVED => '已解决',
                Feedback::STATUS_REJECTED => '已驳回',
                Feedback::STATUS_PROCESSING => '处理中',
                default => $feedback->status,
            };

            $handleNote = mb_substr(strip_tags((string) $feedback->handle_note) ?: '（无处理备注）', 0, 20);
            $feedbackContent = mb_substr(strip_tags((string) $feedback->content), 0, 20);

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

            if (! $result['success']) {
                Log::warning('[订阅消息] 反馈处理推送失败', [
                    'feedback_id' => $feedback->id,
                    'openid' => $user->openid,
                    'errcode' => $result['errcode'],
                    'errmsg' => $result['errmsg'],
                ]);
                $this->writeAudit('feedback_subscribe_failed', $feedback, $result);
            } else {
                $this->writeAudit('feedback_subscribe_sent', $feedback, $result);
            }
        } catch (\Throwable $e) {
            Log::error('[订阅消息] 反馈处理推送异常: ' . $e->getMessage(), [
                'feedback_id' => $feedback->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->markError($feedback, $e->getMessage());
        }
    }

    /**
     * 发布新公告后，按范围推送给用户。
     */
    public function pushAnnouncementPublished(Announcement $announcement): void
    {
        $templateId = config('services.mini_program.announcement_template_id');
        if (empty($templateId)) {
            Log::info('[订阅消息] 公告推送跳过：模板 ID 未配置', ['announcement_id' => $announcement->id]);

            return;
        }

        $users = User::query()->whereNotNull('openid')->active()->get();

        $typeText = match ($announcement->type) {
            Announcement::TYPE_NOTICE => '通知',
            Announcement::TYPE_ACTIVITY => '活动',
            Announcement::TYPE_UPDATE => '版本更新',
            default => '公告',
        };

        $title = mb_substr(strip_tags((string) $announcement->title), 0, 20);
        $content = mb_substr(strip_tags((string) $announcement->content), 0, 20);

        $successCount = 0;
        $failCount = 0;

        foreach ($users as $user) {
            try {
                $data = [
                    'thing1' => ['value' => $typeText],
                    'thing2' => ['value' => $title],
                    'thing3' => ['value' => $content ?: '（点击查看详情）'],
                    'time4' => ['value' => now()->format('Y-m-d H:i')],
                ];

                $result = $this->wechatService->sendSubscribeMessage(
                    openid: $user->openid,
                    templateId: $templateId,
                    data: $data,
                    page: 'pages/announcement/detail?id=' . $announcement->id,
                );

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                    Log::warning('[订阅消息] 公告推送失败', [
                        'announcement_id' => $announcement->id,
                        'user_id' => $user->id,
                        'openid' => $user->openid,
                        'errcode' => $result['errcode'],
                        'errmsg' => $result['errmsg'],
                    ]);
                }
            } catch (\Throwable $e) {
                $failCount++;
                Log::error('[订阅消息] 公告推送异常: ' . $e->getMessage(), [
                    'announcement_id' => $announcement->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        $this->writeAudit('announcement_subscribe_published', $announcement, [
            'total' => $users->count(),
            'success' => $successCount,
            'failed' => $failCount,
        ]);
    }

    /**
     * 发布新通知后，按范围推送给用户（Notification 站内通知）。
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

            // 按 scope 获取接收人列表
            $recipients = match ($notification->scope) {
                'specified' => User::query()
                    ->whereIn('id', $notification->targets ?? [])
                    ->whereNotNull('openid')
                    ->active()
                    ->get(),
                'registered' => User::query()->whereNotNull('openid')->active()->get(),
                default => User::query()->whereNotNull('openid')->active()->get(),
            };

            $title = mb_substr(strip_tags((string) $notification->title), 0, 20);
            $body = mb_substr(strip_tags((string) $notification->body), 0, 20);

            $successCount = 0;
            $failCount = 0;
            $failDetails = [];

            foreach ($recipients as $user) {
                try {
                    $data = [
                        'thing1' => ['value' => '站内通知'],
                        'thing2' => ['value' => $title],
                        'thing3' => ['value' => $body ?: '（点击查看详情）'],
                        'time4' => ['value' => now()->format('Y-m-d H:i')],
                    ];

                    $result = $this->wechatService->sendSubscribeMessage(
                        openid: $user->openid,
                        templateId: $templateId,
                        data: $data,
                        page: 'pages/notification/detail?id=' . $notification->id,
                    );

                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failCount++;
                        $failDetails[] = [
                            'user_id' => $user->id,
                            'errcode' => $result['errcode'],
                            'errmsg' => $result['errmsg'],
                        ];
                    }
                } catch (\Throwable $e) {
                    $failCount++;
                    $failDetails[] = [
                        'user_id' => $user->id,
                        'exception' => $e->getMessage(),
                    ];
                }
            }

            $notification->forceFill([
                'subscribe_sent' => true,
                'subscribe_sent_at' => now(),
                'subscribe_result' => json_encode([
                    'total' => $recipients->count(),
                    'success' => $successCount,
                    'failed' => $failCount,
                    'fail_details' => array_slice($failDetails, 0, 10),
                ], JSON_UNESCAPED_UNICODE),
            ])->save();

            $this->writeAudit('notification_subscribe_sent', $notification, [
                'total' => $recipients->count(),
                'success' => $successCount,
                'failed' => $failCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('[订阅消息] 站内通知推送异常: ' . $e->getMessage(), [
                'notification_id' => $notification->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->markErrorNotification($notification, $e->getMessage());
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
                'module' => $this->resolveModule($model),
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

    protected function resolveModule(object $model): string
    {
        return match (true) {
            $model instanceof Feedback => 'feedback',
            $model instanceof Announcement => 'announcement',
            $model instanceof Notification => 'notification',
            default => 'subscribe_message',
        };
    }
}
