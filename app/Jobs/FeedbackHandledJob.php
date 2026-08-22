<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\SubscribeMessageFailure;
use App\Services\SubscribeMessageService;
use App\Services\WechatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 反馈处理完成后，向提交用户推送处理结果通知（异步队列）。
 *
 * 同一 feedback_id 全局唯一，防止重复入队。
 * 队列失败最多重试 3 次（30s / 2min / 5min），最终失败写入 subscribe_message_failures 表。
 */
class FeedbackHandledJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> 退避策略（秒） */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $feedbackId,
    ) {
    }

    /**
     * 全局唯一键：按 feedback_id 去重，避免同一反馈多次入队。
     */
    public function uniqueId(): string
    {
        return (string) $this->feedbackId;
    }

    public function handle(WechatService $wechatService): void
    {
        /** @var Feedback|null $feedback */
        $feedback = Feedback::query()->find($this->feedbackId);
        if (! $feedback) {
            $this->fail(new \RuntimeException("Feedback #{$this->feedbackId} 不存在"));

            return;
        }

        // 已推送过直接跳过（幂等）
        if ($feedback->subscribe_sent) {
            Log::info('[订阅消息-队列] FeedbackHandledJob 跳过：已推送过', ['feedback_id' => $feedback->id]);

            return;
        }

        $user = $feedback->user;
        if (! $user || empty($user->openid)) {
            $feedback->forceFill([
                'subscribe_sent' => false,
                'subscribe_sent_at' => now(),
                'subscribe_result' => json_encode(['skipped' => true, 'reason' => '无关联用户或 openid 为空'], JSON_UNESCAPED_UNICODE),
            ])->save();

            return;
        }

        $templateId = config('services.mini_program.feedback_template_id');
        if (empty($templateId)) {
            $feedback->forceFill([
                'subscribe_sent' => false,
                'subscribe_sent_at' => now(),
                'subscribe_result' => json_encode(['skipped' => true, 'reason' => '反馈处理模板 ID 未配置'], JSON_UNESCAPED_UNICODE),
            ])->save();

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

        $result = $wechatService->sendSubscribeMessage(
            openid: $user->openid,
            templateId: $templateId,
            data: $data,
            page: 'pages/feedback/detail?id=' . $feedback->id,
        );

        $feedback->forceFill([
            'subscribe_sent' => true,
            'subscribe_sent_at' => now(),
            'subscribe_result' => json_encode($result, JSON_UNESCAPED_UNICODE),
        ])->save();

        if (! $result['success']) {
            Log::warning('[订阅消息-队列] 反馈处理推送失败', [
                'feedback_id' => $feedback->id,
                'openid' => $user->openid,
                'errcode' => $result['errcode'],
                'errmsg' => $result['errmsg'],
                'attempts' => $this->attempts(),
            ]);
            $this->writeAudit('feedback_subscribe_failed', $feedback, $result);

            // 业务级错误（如用户未订阅 43101）不再重试，直接记录失败表
            $noRetryCodes = [43101, 40037, 41030, 40003, -1, -2];
            if (in_array($result['errcode'], $noRetryCodes, true)) {
                $this->recordFailure($feedback, $user->openid, $templateId, $data, $result);
                $this->delete();

                return;
            }

            // 其他错误抛出以便队列重试
            throw new \RuntimeException(
                sprintf(
                    '反馈订阅推送失败 errcode=%d errmsg=%s (attempt %d/%d)',
                    $result['errcode'],
                    $result['errmsg'],
                    $this->attempts(),
                    $this->tries
                )
            );
        }

        $this->writeAudit('feedback_subscribe_sent', $feedback, $result);
    }

    /**
     * 队列任务最终失败（超过最大重试次数或其他异常）。
     */
    public function failed(\Throwable $exception): void
    {
        $feedback = Feedback::query()->find($this->feedbackId);
        if (! $feedback) {
            Log::error('[订阅消息-队列] FeedbackHandledJob 最终失败，但 Feedback 已不存在', ['feedback_id' => $this->feedbackId, 'msg' => $exception->getMessage()]);

            return;
        }

        $openid = $feedback->user?->openid ?? '';
        $templateId = (string) config('services.mini_program.feedback_template_id', '');
        $this->recordFailure($feedback, $openid, $templateId, [], [
            'errcode' => -999,
            'errmsg' => '队列最终失败: ' . $exception->getMessage(),
            'exception_class' => get_class($exception),
        ]);

        // 即使最终失败，也标记 subscribe_sent=true，避免下次又入队重试无限循环
        $feedback->forceFill([
            'subscribe_sent' => true,
            'subscribe_sent_at' => now(),
            'subscribe_result' => json_encode([
                'errcode' => -999,
                'errmsg' => '队列最终失败: ' . $exception->getMessage(),
                'failed_at' => now()->toISOString(),
            ], JSON_UNESCAPED_UNICODE),
        ])->save();
    }

    protected function writeAudit(string $action, Feedback $feedback, array $meta): void
    {
        try {
            AuditLog::query()->create([
                'type' => 'subscribe_message',
                'module' => 'feedback',
                'action' => $action,
                'description' => '微信订阅消息推送（队列）',
                'subject_type' => Feedback::class,
                'subject_id' => $feedback->id,
                'new_data' => $meta,
                'user_id' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[订阅消息-队列] 审计日志写入失败: ' . $e->getMessage());
        }
    }

    /**
     * 写入失败记录表，便于后台人工排查/重发。
     */
    protected function recordFailure(Feedback $feedback, string $openid, string $templateId, array $data, array $result): void
    {
        try {
            SubscribeMessageFailure::query()->create([
                'job_uuid' => $this->job?->uuid ?? null,
                'scene' => 'feedback_handled',
                'subject_type' => Feedback::class,
                'subject_id' => $feedback->id,
                'openid' => $openid,
                'template_id' => $templateId,
                'payload' => ['data' => $data, 'page' => 'pages/feedback/detail?id=' . $feedback->id],
                'page' => 'pages/feedback/detail?id=' . $feedback->id,
                'attempts' => $this->attempts(),
                'last_errcode' => $result['errcode'] ?? null,
                'last_errmsg' => $result['errmsg'] ?? null,
                'last_attempted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[订阅消息-队列] 失败记录表写入失败: ' . $e->getMessage());
        }
    }
}
