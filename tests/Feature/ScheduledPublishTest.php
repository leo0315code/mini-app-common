<?php

namespace Tests\Feature;

use App\Console\Commands\PublishScheduled;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledPublishTest extends TestCase
{
    use RefreshDatabase;

    /**
     * publish:scheduled 自动发布「草稿 + published_at 已过」的公告
     */
    public function test_command_publishes_overdue_announcement(): void
    {
        $pastDue = Announcement::factory()->create([
            'status' => Announcement::STATUS_DRAFT,
            'published_at' => now()->subMinutes(5),
        ]);
        $future = Announcement::factory()->create([
            'status' => Announcement::STATUS_DRAFT,
            'published_at' => now()->addMinutes(5),
        ]);
        $alreadyPublished = Announcement::factory()->create([
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now()->subMinutes(10),
        ]);

        $this->artisan(PublishScheduled::class)
            ->assertExitCode(0)
            ->expectsOutputToContain('公告 1 条');

        $this->assertSame(Announcement::STATUS_PUBLISHED, $pastDue->fresh()->status);
        $this->assertSame(Announcement::STATUS_DRAFT, $future->fresh()->status);
        $this->assertSame(Announcement::STATUS_PUBLISHED, $alreadyPublished->fresh()->status);
    }

    /**
     * publish:scheduled 自动发布 scheduled_at 到期的通知，并同步派发接收人
     */
    public function test_command_publishes_overdue_notification_and_dispatches_recipients(): void
    {
        $users = User::factory()->count(3)->create();

        $scheduled = Notification::factory()->create([
            'published' => false,
            'published_at' => now()->subMinutes(3),
            'scope' => 'specified',
            'targets' => $users->pluck('id')->values()->all(),
        ]);

        $this->artisan(PublishScheduled::class)
            ->assertExitCode(0)
            ->expectsOutputToContain('通知 1 条');

        $scheduled->refresh();
        $this->assertTrue($scheduled->published);
        $this->assertCount(3, $scheduled->recipients);
        // 确保 recipients pivot 写入的 user_id 都不是 null 且在目标用户中
        $recipientUserIds = \DB::table('notification_user')
            ->where('notification_id', $scheduled->id)
            ->pluck('user_id')
            ->filter(fn ($id) => $id !== null)
            ->values()
            ->all();
        $this->assertCount(3, $recipientUserIds);
        $this->assertEqualsCanonicalizing(
            $users->pluck('id')->all(),
            $recipientUserIds,
        );
    }

    /**
     * publish:scheduled 没有任何到期内容时，输出含 0 条提示
     */
    public function test_command_reports_zero_when_nothing_pending(): void
    {
        $this->artisan(PublishScheduled::class)
            ->assertExitCode(0)
            ->expectsOutputToContain('定时发布完成：公告 0 条，通知 0 条');
    }
}