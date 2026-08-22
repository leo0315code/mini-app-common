<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\SubscribeMessageFailure;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 发布站内通知后，按 scope 展开目标用户并派发单用户子 Job。
 *
 * 为避免大 Job 超时，与公告场景一致：本 Job 只负责「选用户 + 派发单用户 Job」。
 */
class NotificationPublishedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [60, 300];

    private const CHUNK_SIZE = 100;

    public function __construct(
        public int $notificationId,
    ) {
    }

    public function handle(): void
    {
        /** @var Notification|null $notification */
        $notification = Notification::query()->find($this->notificationId);
        if (! $notification) {
            $this->fail(new \RuntimeException("Notification #{$this->notificationId} 不存在"));

            return;
        }

        // 幂等：已发送过直接跳过
        if ($notification->subscribe_sent) {
            Log::info('[订阅消息-队列] NotificationPublishedJob 跳过：已推送过', ['notification_id' => $notification->id]);

            return;
        }

        $templateId = config('services.mini_program.announcement_template_id');
        if (empty($templateId)) {
            $notification->forceFill([
                'subscribe_sent' => false,
                'subscribe_sent_at' => now(),
                'subscribe_result' => json_encode(['skipped' => true, 'reason' => '公告/通知模板 ID 未配置'], JSON_UNESCAPED_UNICODE),
            ])->save();

            return;
        }

        $baseQuery = User::query()->whereNotNull('openid')->active();

        $targetIds = [];
        if ($notification->scope === 'specified') {
            $targetIds = $notification->targets ?? [];
            if (empty($targetIds)) {
                $notification->forceFill([
                    'subscribe_sent' => true,
                    'subscribe_sent_at' => now(),
                    'subscribe_result' => json_encode([
                        'total' => 0,
                        'dispatched' => 0,
                        'reason' => 'scope=specified 但 targets 为空',
                    ], JSON_UNESCAPED_UNICODE),
                ])->save();

                return;
            }
            $baseQuery->whereIn('id', $targetIds);
        } elseif ($notification->scope === 'registered') {
            // registered = whereNotNull('openid')，已在 baseQuery 中限制
        }
        // scope=all / default 同 baseQuery

        $title = mb_substr(strip_tags((string) $notification->title), 0, 20);
        $body = mb_substr(strip_tags((string) $notification->body), 0, 20);

        $templateData = [
            'thing1' => ['value' => '站内通知'],
            'thing2' => ['value' => $title],
            'thing3' => ['value' => $body ?: '（点击查看详情）'],
            'time4' => ['value' => now()->format('Y-m-d H:i')],
        ];

        $page = 'pages/notification/detail?id=' . $notification->id;

        $total = 0;
        $dispatched = 0;
        $failDetails = [];

        $baseQuery->orderBy('id')->chunk(self::CHUNK_SIZE, function (\Illuminate\Database\Eloquent\Collection $users) use (
            $templateId,
            $templateData,
            $page,
            $notification,
            &$total,
            &$dispatched,
            &$failDetails,
        ) {
            foreach ($users as $user) {
                $total++;
                try {
                    SendSubscribeMessageToUserJob::dispatch(
                        scene: 'notification_published',
                        subject: $notification,
                        openid: $user->openid,
                        templateId: $templateId,
                        data: $templateData,
                        page: $page,
                        options: [],
                    )->onQueue($this->queue ?? 'default');
                    $dispatched++;
                } catch (\Throwable $e) {
                    $failDetails[] = ['user_id' => $user->id, 'reason' => $e->getMessage()];
                    $this->recordDispatchFailure($notification, $user, $templateId, $templateData, $page, $e);
                }
            }
        });

        $notification->forceFill([
            'subscribe_sent' => true,
            'subscribe_sent_at' => now(),
            'subscribe_result' => json_encode([
                'total' => $total,
                'dispatched' => $dispatched,
                'dispatch_failed' => $total - $dispatched,
                'fail_details' => array_slice($failDetails, 0, 10),
                'note' => '已派发子队列，实际推送结果由子 Job 异步确认',
            ], JSON_UNESCAPED_UNICODE),
        ])->save();

        try {
            AuditLog::query()->create([
                'type' => 'subscribe_message',
                'module' => 'notification',
                'action' => 'notification_subscribe_dispatched',
                'description' => '站内通知订阅消息已分发到子队列',
                'subject_type' => Notification::class,
                'subject_id' => $notification->id,
                'new_data' => [
                    'total_users' => $total,
                    'dispatched' => $dispatched,
                    'dispatch_failed' => $total - $dispatched,
                    'scope' => $notification->scope,
                ],
                'user_id' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[订阅消息-队列] 站内通知分发审计写入失败: ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[订阅消息-队列] NotificationPublishedJob 最终失败: ' . $exception->getMessage(), [
            'notification_id' => $this->notificationId,
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    protected function recordDispatchFailure(
        Notification $notification,
        User $user,
        string $templateId,
        array $data,
        string $page,
        \Throwable $e,
    ): void {
        try {
            SubscribeMessageFailure::query()->create([
                'scene' => 'notification_published',
                'subject_type' => Notification::class,
                'subject_id' => $notification->id,
                'openid' => $user->openid,
                'template_id' => $templateId,
                'payload' => ['data' => $data, 'page' => $page],
                'page' => $page,
                'attempts' => 0,
                'last_errcode' => null,
                'last_errmsg' => '派发失败: ' . $e->getMessage(),
                'last_attempted_at' => now(),
            ]);
        } catch (\Throwable $_) {
        }
    }
}
