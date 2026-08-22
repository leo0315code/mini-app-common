<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\SubscribeMessageFailure;
use App\Services\WechatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 通用：向单个用户发送一条订阅消息。
 *
 * 被 AnnouncementPublishedJob / NotificationPublishedJob 批量分发，
 * 也可以独立使用。按 (openid + scene + subject_key) 唯一去重，
 * 避免同一消息重复投递。
 */
class SendSubscribeMessageToUserJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> 退避策略（秒） */
    public array $backoff = [20, 90, 300];

    /** @var class-string<Model>|null */
    public ?string $subjectType;

    public ?int $subjectId;

    /**
     * @param  string  $scene  场景名（用于审计/失败记录）：announcement_published / notification_published / direct
     * @param  Model|null  $subject  关联模型（公告/站内通知等），用于失败记录 MorphTo
     * @param  string  $openid  用户 openid
     * @param  string  $templateId  模板 ID
     * @param  array  $data  模板数据
     * @param  string|null  $page  跳转页面
     * @param  array  $options  额外选项（miniprogram_state / lang）
     */
    public function __construct(
        public string $scene,
        ?Model $subject,
        public string $openid,
        public string $templateId,
        public array $data,
        public ?string $page = null,
        public array $options = [],
    ) {
        $this->subjectType = $subject ? $subject::class : null;
        $this->subjectId = $subject?->id;
    }

    /**
     * 唯一键：(scene, subject_type, subject_id, openid) 组合。
     * 这样同一场景下同一公告/通知不会对同一 openid 重复发送。
     */
    public function uniqueId(): string
    {
        return md5(implode('|', [
            $this->scene,
            (string) $this->subjectType,
            (string) $this->subjectId,
            $this->openid,
        ]));
    }

    public function handle(WechatService $wechatService): void
    {
        if (empty($this->templateId)) {
            Log::warning('[订阅消息-队列] SendSubscribeMessageToUserJob 跳过：空模板ID', [
                'scene' => $this->scene,
                'openid' => $this->openid,
            ]);
            $this->delete();

            return;
        }

        $result = $wechatService->sendSubscribeMessage(
            openid: $this->openid,
            templateId: $this->templateId,
            data: $this->data,
            page: $this->page,
            options: $this->options,
        );

        if ($result['success']) {
            $this->writeAudit('subscribe_sent', $result);

            return;
        }

        Log::warning('[订阅消息-队列] 单用户推送失败', [
            'scene' => $this->scene,
            'openid' => $this->openid,
            'errcode' => $result['errcode'],
            'errmsg' => $result['errmsg'],
            'attempts' => $this->attempts(),
        ]);
        $this->writeAudit('subscribe_failed', $result);

        // 业务级错误不重试，直接写失败表
        $noRetryCodes = [43101, 40037, 41030, 40003, -1, -2];
        if (in_array($result['errcode'], $noRetryCodes, true)) {
            $this->recordFailure($result);
            $this->delete();

            return;
        }

        // 其余错误抛出以启用队列重试
        throw new \RuntimeException(sprintf(
            '%s 推送失败 errcode=%d errmsg=%s (attempt %d/%d)',
            $this->scene,
            $result['errcode'],
            $result['errmsg'],
            $this->attempts(),
            $this->tries
        ));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[订阅消息-队列] SendSubscribeMessageToUserJob 最终失败', [
            'scene' => $this->scene,
            'openid' => $this->openid,
            'template_id' => $this->templateId,
            'msg' => $exception->getMessage(),
            'exception_class' => get_class($exception),
        ]);

        $this->recordFailure([
            'errcode' => -999,
            'errmsg' => '队列最终失败: ' . $exception->getMessage(),
            'exception_class' => get_class($exception),
        ]);
    }

    protected function writeAudit(string $action, array $meta): void
    {
        if ($this->subjectType === null || $this->subjectId === null) {
            return;
        }

        try {
            AuditLog::query()->create([
                'type' => 'subscribe_message',
                'module' => $this->resolveModule($this->subjectType),
                'action' => $this->scene . '_' . $action,
                'description' => '单用户订阅消息推送（队列）',
                'subject_type' => $this->subjectType,
                'subject_id' => $this->subjectId,
                'new_data' => array_merge($meta, [
                    'openid' => $this->maskOpenid($this->openid),
                    'attempts' => $this->attempts(),
                ]),
                'user_id' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[订阅消息-队列] 单用户审计写入失败: ' . $e->getMessage());
        }
    }

    protected function recordFailure(array $result): void
    {
        try {
            SubscribeMessageFailure::query()->create([
                'job_uuid' => $this->job?->uuid ?? null,
                'scene' => $this->scene,
                'subject_type' => $this->subjectType,
                'subject_id' => $this->subjectId,
                'openid' => $this->openid,
                'template_id' => $this->templateId,
                'payload' => ['data' => $this->data, 'page' => $this->page, 'options' => $this->options],
                'page' => $this->page,
                'attempts' => $this->attempts(),
                'last_errcode' => $result['errcode'] ?? null,
                'last_errmsg' => $result['errmsg'] ?? null,
                'last_attempted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[订阅消息-队列] 单用户失败表写入失败: ' . $e->getMessage());
        }
    }

    /**
     * @param  class-string<Model>  $subjectType
     */
    protected function resolveModule(string $subjectType): string
    {
        return match ($subjectType) {
            \App\Models\Announcement::class => 'announcement',
            \App\Models\Notification::class => 'notification',
            \App\Models\Feedback::class => 'feedback',
            default => 'subscribe_message',
        };
    }

    protected function maskOpenid(string $openid): string
    {
        if (mb_strlen($openid) <= 6) {
            return $openid;
        }

        return mb_substr($openid, 0, 4) . '***' . mb_substr($openid, -2);
    }
}
