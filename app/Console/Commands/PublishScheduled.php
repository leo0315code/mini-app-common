<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishScheduled extends Command
{
    protected $signature = 'publish:scheduled';

    protected $description = '自动发布设置了发布时间的公告与站内通知（草稿 + published_at <= now）';

    public function handle(): int
    {
        $announcementCount = $this->publishAnnouncements();
        $notificationCount = $this->publishNotifications();

        $this->info(sprintf(
            '[%s] 定时发布完成：公告 %d 条，通知 %d 条',
            now()->format('Y-m-d H:i:s'),
            $announcementCount,
            $notificationCount,
        ));

        return self::SUCCESS;
    }

    protected function publishAnnouncements(): int
    {
        $pending = Announcement::query()
            ->where('status', Announcement::STATUS_DRAFT)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($pending): void {
            foreach ($pending as $announcement) {
                $announcement->update([
                    'status' => Announcement::STATUS_PUBLISHED,
                ]);
            }
        });

        return $pending->count();
    }

    protected function publishNotifications(): int
    {
        $pending = Notification::query()
            ->where('published', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($pending): void {
            foreach ($pending as $notification) {
                $notification->update([
                    'published' => true,
                ]);

                $notification->dispatchToRecipients();
            }
        });

        return $pending->count();
    }
}