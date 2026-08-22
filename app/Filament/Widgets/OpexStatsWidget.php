<?php

namespace App\Filament\Widgets;

use App\Models\Feedback;
use App\Models\Media;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class OpexStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // 待处理反馈（pending）
        $pendingFeedback = Feedback::query()->where('status', Feedback::STATUS_PENDING)->count();

        // 通知已读率（基于回执表 notification_user）
        $receiptTotal = DB::table('notification_user')->count();
        $receiptRead = DB::table('notification_user')->where('read', true)->count();
        $readRate = $receiptTotal > 0
            ? round($receiptRead / $receiptTotal * 100, 1) . '%'
            : '0%';

        // 内容数量：公告 + 文章
        $announcements = \App\Models\Announcement::query()->count();
        $articles = \App\Models\Article::query()->count();

        // 媒体存储占用（字节 → 可读）
        $mediaBytes = (int) Media::query()->sum('size');
        $mediaCount = Media::query()->count();

        // 今日 API 调用量（token 今日被使用过）
        $todayStart = now()->startOfDay();
        $apiCallsToday = PersonalAccessToken::query()
            ->whereNotNull('last_used_at')
            ->where('last_used_at', '>=', $todayStart)
            ->count();

        // 封禁用户数
        $bannedUsers = User::query()->where('status', User::STATUS_BANNED)->count();

        return [
            Stat::make('待处理反馈', $pendingFeedback)
                ->description('pending 状态待运营处理')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color($pendingFeedback > 0 ? 'warning' : 'success'),

            Stat::make('通知已读率', $readRate)
                ->description($receiptRead . ' / ' . $receiptTotal . ' 条回执已读')
                ->icon('heroicon-o-bell-alert')
                ->color('info'),

            Stat::make('内容总量', $announcements + $articles)
                ->description("公告 {$announcements} · 文章 {$articles}")
                ->icon('heroicon-o-document-text')
                ->color('primary'),

            Stat::make('媒体占用', $this->formatBytes($mediaBytes))
                ->description($mediaCount . ' 个文件')
                ->icon('heroicon-o-photo')
                ->color('gray'),

            Stat::make('今日 API 调用', $apiCallsToday)
                ->description('token 今日活跃')
                ->icon('heroicon-o-arrow-path')
                ->color('success'),

            Stat::make('封禁用户', $bannedUsers)
                ->description('已被禁用登录')
                ->icon('heroicon-o-no-symbol')
                ->color($bannedUsers > 0 ? 'danger' : 'success'),
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);

        return round($bytes / (1024 ** $factor), 1) . ' ' . $units[$factor];
    }
}
