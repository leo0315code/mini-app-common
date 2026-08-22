<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\SubscribeMessageFailure;
use App\Models\User;
use App\Services\WechatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 发布新公告后，按范围（所有 openid + active 用户）推送订阅消息。
 *
 * 为避免单个大 Job 执行超时，本 Job 只做「分页拉用户 + 分发单用户子 Job」。
 * 真正的推送逻辑由 SendSubscribeMessageToUserJob 执行。
 */
class AnnouncementPublishedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [60, 300];

    private const CHUNK_SIZE = 100;

    public function __construct(
        public int $announcementId,
    ) {
    }

    public function handle(): void
    {
        /** @var Announcement|null $announcement */
        $announcement = Announcement::query()->find($this->announcementId);
        if (! $announcement) {
            $this->fail(new \RuntimeException("Announcement #{$this->announcementId} 不存在"));

            return;
        }

        $templateId = config('services.mini_program.announcement_template_id');
        if (empty($templateId)) {
            Log::info('[订阅消息-队列] AnnouncementPublishedJob 跳过：公告模板 ID 未配置', ['announcement_id' => $announcement->id]);

            return;
        }

        $typeText = match ($announcement->type) {
            Announcement::TYPE_NOTICE => '通知',
            Announcement::TYPE_ACTIVITY => '活动',
            Announcement::TYPE_UPDATE => '版本更新',
            default => '公告',
        };

        $title = mb_substr(strip_tags((string) $announcement->title), 0, 20);
        $content = mb_substr(strip_tags((string) $announcement->content), 0, 20);

        $templateData = [
            'thing1' => ['value' => $typeText],
            'thing2' => ['value' => $title],
            'thing3' => ['value' => $content ?: '（点击查看详情）'],
            'time4' => ['value' => now()->format('Y-m-d H:i')],
        ];

        $page = 'pages/announcement/detail?id=' . $announcement->id;

        $total = 0;
        $successDispatch = 0;

        // 分页遍历用户，避免内存占用过大
        User::query()
            ->whereNotNull('openid')
            ->where('status', User::STATUS_NORMAL)
            ->orderBy('id')
            ->chunk(self::CHUNK_SIZE, function (\Illuminate\Database\Eloquent\Collection $users) use (
                $templateId,
                $templateData,
                $page,
                $announcement,
                &$total,
                &$successDispatch,
            ) {
                foreach ($users as $user) {
                    $total++;
                    try {
                        SendSubscribeMessageToUserJob::dispatch(
                            scene: 'announcement_published',
                            subject: $announcement,
                            openid: $user->openid,
                            templateId: $templateId,
                            data: $templateData,
                            page: $page,
                            options: [],
                        )->onQueue($this->queue ?? 'default');
                        $successDispatch++;
                    } catch (\Throwable $e) {
                        Log::warning('[订阅消息-队列] 公告子 Job 派发失败', [
                            'announcement_id' => $announcement->id,
                            'user_id' => $user->id,
                            'msg' => $e->getMessage(),
                        ]);
                        $this->recordDispatchFailure($announcement, $user, $templateId, $templateData, $page, $e);
                    }
                }
            });

        // 审计：记录分发统计
        try {
            AuditLog::query()->create([
                'type' => 'subscribe_message',
                'module' => 'announcement',
                'action' => 'announcement_subscribe_dispatched',
                'description' => '公告订阅消息已分发到子队列',
                'subject_type' => Announcement::class,
                'subject_id' => $announcement->id,
                'new_data' => [
                    'total_users' => $total,
                    'dispatched' => $successDispatch,
                    'dispatch_failed' => $total - $successDispatch,
                ],
                'user_id' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[订阅消息-队列] 公告分发审计写入失败: ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[订阅消息-队列] AnnouncementPublishedJob 最终失败: ' . $exception->getMessage(), [
            'announcement_id' => $this->announcementId,
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    protected function recordDispatchFailure(
        Announcement $announcement,
        User $user,
        string $templateId,
        array $data,
        string $page,
        \Throwable $e,
    ): void {
        try {
            SubscribeMessageFailure::query()->create([
                'scene' => 'announcement_published',
                'subject_type' => Announcement::class,
                'subject_id' => $announcement->id,
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
