<?php

namespace Tests\Unit;

use App\Models\Announcement;
use App\Models\Feedback;
use App\Models\Notification;
use App\Support\SubscribeMessageTemplate;
use PHPUnit\Framework\TestCase;

/**
 * 订阅消息公共拼装工具（去重后抽离）：纯函数，覆盖脱敏 / 模块解析 / 截断三类逻辑。
 */
class SubscribeMessageTemplateTest extends TestCase
{
    // ---------- maskOpenid ----------

    public function test_mask_openid_short_returns_as_is(): void
    {
        $this->assertSame('abc', SubscribeMessageTemplate::maskOpenid('abc'));
        $this->assertSame('123456', SubscribeMessageTemplate::maskOpenid('123456'));
    }

    public function test_mask_openid_masks_middle(): void
    {
        $masked = SubscribeMessageTemplate::maskOpenid('openidabcdefgh');
        // 前 4 + *** + 后 2
        $this->assertSame('open***gh', $masked);
        $this->assertStringNotContainsString('abcdef', $masked);
    }

    // ---------- resolveModule ----------

    public function test_resolve_module_by_class_string(): void
    {
        $this->assertSame('announcement', SubscribeMessageTemplate::resolveModule(Announcement::class));
        $this->assertSame('notification', SubscribeMessageTemplate::resolveModule(Notification::class));
        $this->assertSame('feedback', SubscribeMessageTemplate::resolveModule(Feedback::class));
    }

    public function test_resolve_module_by_instance(): void
    {
        $this->assertSame('announcement', SubscribeMessageTemplate::resolveModule(new Announcement()));
        $this->assertSame('feedback', SubscribeMessageTemplate::resolveModule(new Feedback()));
    }

    public function test_resolve_module_falls_back_to_default(): void
    {
        $this->assertSame('subscribe_message', SubscribeMessageTemplate::resolveModule(\stdClass::class));
    }

    // ---------- truncate ----------

    public function test_truncate_strips_tags_and_cuts_length(): void
    {
        $this->assertSame('标题前段', SubscribeMessageTemplate::truncate('<b>标题前段</b>后段超出', 4));
    }

    public function test_truncate_handles_null(): void
    {
        $this->assertSame('', SubscribeMessageTemplate::truncate(null));
        $this->assertSame('', SubscribeMessageTemplate::truncate(null, 20));
    }

    public function test_truncate_default_length_20(): void
    {
        $long = str_repeat('中', 30);
        $this->assertSame(20, mb_strlen(SubscribeMessageTemplate::truncate($long)));
    }
}
