<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * 操作审计便捷写入服务。
 *
 * 用法：
 *   Audit::log('update', 'user', '编辑用户 #10', ['old'=>...,'new'=>...], $user, $record);
 */
class Audit
{
    /**
     * 记录一条审计日志。
     *
     * @param  string  $type  操作类型（create/update/delete/login/config...）
     * @param  string  $module  模块（user/token/announcement/feedback/system...）
     * @param  string|null  $action  具体动作描述
     * @param  array<string, mixed>|null  $oldData  变更前
     * @param  array<string, mixed>|null  $newData  变更后
     * @param  User|null  $user  操作人
     * @param  Model|null  $subject  被操作对象
     */
    public static function log(
        string $type,
        string $module,
        ?string $action = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?User $user = null,
        ?Model $subject = null
    ): AuditLog {
        $user ??= Request::user();

        return AuditLog::create([
            'type' => $type,
            'module' => $module,
            'action' => $action,
            'description' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
            'user_id' => $user?->id,
            'url' => Request::fullUrl(),
            'ip' => Request::ip(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);
    }
}
