<?php

namespace App\Console\Commands;

use App\Jobs\SendSubscribeMessageToUserJob;
use App\Models\SubscribeMessageFailure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 重试失败的订阅消息（subscribe:retry-failed）。
 *
 * 失败表里 resolved_at 为空的记录，用其原始 payload 重新入队发送；
 * 达到最大尝试次数或命中业务级不可重试错误码（43101 等）则标记放弃。
 *
 * 用法：
 *   php artisan subscribe:retry-failed            # 默认重试前 100 条
 *   php artisan subscribe:retry-failed --limit=50 # 分批
 *   php artisan subscribe:retry-failed --dry-run  # 仅预览将重试的记录，不实际发送
 */
class RetryFailedSubscribeMessages extends Command
{
    protected $signature = 'subscribe:retry-failed
        {--limit=100 : 本次最多重试条数}
        {--max-attempts=3 : 单条记录最大尝试次数，超过则标记放弃}
        {--dry-run : 仅预览，不实际入队}';

    protected $description = '重试失败表中未解决的订阅消息（resolved_at IS NULL）';

    /** 业务级不可重试错误码：与 SendSubscribeMessageToUserJob::$noRetryCodes 保持一致 */
    protected const NO_RETRY_CODES = [43101, 40037, 41030, 40003, -1, -2];

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $maxAttempts = (int) $this->option('max-attempts');
        $dryRun = (bool) $this->option('dry-run');

        $pending = SubscribeMessageFailure::query()
            ->whereNull('resolved_at')
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('没有待重试的失败记录。');

            return self::SUCCESS;
        }

        $this->info(sprintf('待重试 %d 条（limit=%d, max-attempts=%d%s）',
            $pending->count(), $limit, $maxAttempts, $dryRun ? '，DRY-RUN 仅预览' : ''));

        $dispatched = 0;
        $abandoned = 0;

        foreach ($pending as $failure) {
            $payload = is_array($failure->payload)
                ? $failure->payload
                : (json_decode((string) $failure->payload, true) ?: []);

            // 不可重试的错误码（如用户已取消订阅）→ 直接标记放弃，不浪费队列
            $errcode = (int) ($failure->last_errcode ?? 0);
            if (in_array($errcode, self::NO_RETRY_CODES, true)) {
                $abandoned++;
                $this->line(sprintf('  #%d 放弃：业务级错误码 %d（%s）', $failure->id, $errcode, $failure->last_errmsg));

                if (! $dryRun) {
                    $this->markAbandoned($failure, '业务级不可重试错误码 '.$errcode);
                }

                continue;
            }

            $this->line(sprintf('  #%d 重试：scene=%s openid=%s attempts=%d→%d',
                $failure->id, $failure->scene, $this->maskOpenid($failure->openid),
                $failure->attempts, $failure->attempts + 1));

            if ($dryRun) {
                continue;
            }

            // 重新入队发送；Job 内部成功/失败会走完整审计链路
            SendSubscribeMessageToUserJob::dispatch(
                scene: $failure->scene,
                subject: $this->resolveSubject($failure),
                openid: $failure->openid,
                templateId: $failure->template_id,
                data: $payload['data'] ?? [],
                page: ($payload['page'] ?? null) ?: $failure->page,
                options: $payload['options'] ?? [],
            )->onQueue($failure->scene);

            // 重试次数 +1（无论最终成败，Job 成功时也会把记录标记已解决）
            $failure->increment('attempts');
            $failure->update(['last_attempted_at' => now()]);

            $dispatched++;
        }

        $this->info(sprintf('完成：入队重试 %d 条，标记放弃 %d 条。', $dispatched, $abandoned));

        return self::SUCCESS;
    }

    protected function resolveSubject(SubscribeMessageFailure $failure): ?object
    {
        if (! $failure->subject_type || ! $failure->subject_id) {
            return null;
        }

        try {
            $model = $failure->subject_type::find($failure->subject_id);

            return $model ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function markAbandoned(SubscribeMessageFailure $failure, string $note): void
    {
        DB::table('subscribe_message_failures')
            ->where('id', $failure->id)
            ->update([
                'resolved_at' => now(),
                'resolved_note' => '自动放弃：'.$note,
            ]);
    }

    protected function maskOpenid(string $openid): string
    {
        if (strlen($openid) <= 8) {
            return '***';
        }

        return substr($openid, 0, 4).'***'.substr($openid, -4);
    }
}
