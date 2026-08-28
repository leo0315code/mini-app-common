<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Feedback;
use App\Models\Notification;

/**
 * 订阅消息公共拼装工具：去重 SubscribeMessageService 与 SendSubscribeMessageToUserJob
 * 中重复出现的「openid 脱敏」「模块名解析」「模板字段截断」逻辑。
 */
class SubscribeMessageTemplate
{
    /**
     * 脱敏 openid（仅日志/审计展示，避免明文落库）。
     */
    public static function maskOpenid(string $openid): string
    {
        if (mb_strlen($openid) <= 6) {
            return $openid;
        }

        return mb_substr($openid, 0, 4) . '***' . mb_substr($openid, -2);
    }

    /**
     * 由模型类/实例解析审计模块名（announcement / notification / feedback / subscribe_message）。
     *
     * @param  class-string<Model>|object  $subject
     */
    public static function resolveModule(string|object $subject): string
    {
        $class = is_object($subject) ? $subject::class : $subject;

        return match ($class) {
            Announcement::class => 'announcement',
            Notification::class => 'notification',
            Feedback::class => 'feedback',
            default => 'subscribe_message',
        };
    }

    /**
     * 模板字段截断：去 HTML 标签后按字符数截断（微信订阅消息单字段有长度上限）。
     */
    public static function truncate(?string $value, int $length = 20): string
    {
        return mb_substr(strip_tags((string) $value), 0, $length);
    }
}
